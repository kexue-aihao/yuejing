<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AppSettingService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function loginEndpoint(Request $request)
    {
        return $request->isMethod('get') ? $this->loginPage() : $this->login($request);
    }

    protected function loginPage()
    {
        return view('pages.auth.login');
    }

    protected function registerPage()
    {
        return view('pages.auth.register');
    }

    public function registerEndpoint(Request $request, AppSettingService $settings)
    {
        return $request->isMethod('get') ? $this->registerPage() : $this->register($request, $settings);
    }

    public function forgotPasswordPage()
    {
        return view('pages.auth.forgot-password');
    }

    public function resetPasswordPage(Request $request, string $token)
    {
        return view('pages.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function register(Request $request, AppSettingService $settings)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'string', Rule::in(['user', 'author'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'user',
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        $verificationRequired = filter_var($settings->get(
            'email_verification_required',
            config('yuejing.email_verification.required', false),
        ), FILTER_VALIDATE_BOOLEAN);

        if ($verificationRequired) {
            try {
                event(new Registered($user));
            } catch (\Throwable $exception) {
                Log::warning('Registration email verification could not be sent.', [
                    'user_id' => $user->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->audit($request, $user, 'auth.registered');

        if (! $this->wantsJson($request)) {
            return redirect()->route('dashboard')->with('status', $verificationRequired
                ? __('ui.messages.registration_verified')
                : __('ui.messages.registration'));
        }

        return response()->json([
            'message' => __('ui.messages.registration'),
            'email_verification_required' => $verificationRequired,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->regenerate();
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $request->session()->forget(['pending_two_factor', 'pending_two_factor_user_id', 'pending_two_factor_remember']);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], (bool) ($credentials['remember'] ?? false))) {
            $this->audit($request, null, 'auth.login_failed');
            if (! $this->wantsJson($request)) {
                return back()->withErrors(['email' => __('ui.messages.invalid_credentials')])->withInput();
            }

            return response()->json(['message' => __('ui.messages.invalid_credentials')], 422);
        }

        $user = $request->user();
        if ($user->twoFactorSetting?->enabled) {
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put('pending_two_factor', [
                'user_id' => $user->id,
                'remember' => (bool) ($credentials['remember'] ?? false),
                'expires_at' => now()->addMinutes((int) config('yuejing.two_factor.challenge_lifetime', 10))->timestamp,
                'attempts' => 0,
            ]);
            // Keep legacy session keys for clients that only inspect challenge state.
            $request->session()->put('pending_two_factor_user_id', $user->id);
            $request->session()->put('pending_two_factor_remember', (bool) ($credentials['remember'] ?? false));
            $this->audit($request, $user, 'auth.two_factor_required');

            if (! $this->wantsJson($request)) {
                return redirect()->route('two-factor.challenge');
            }

            return response()->json([
                'message' => __('ui.messages.two_factor_setup'),
                'two_factor_required' => true,
                'challenge_url' => route('two-factor.challenge'),
            ], 202);
        }

        $request->session()->regenerate();
        $this->audit($request, $user, 'auth.logged_in');

        if (! $this->wantsJson($request)) {
            return redirect()->intended(route('dashboard'))->with('status', __('ui.messages.login_success'));
        }

        return response()->json(['message' => __('ui.messages.login_success'), 'user' => $user]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->audit($request, $user, 'auth.logged_out');

        if (! $this->wantsJson($request)) {
            return redirect()->route('home')->with('status', __('ui.messages.logout'));
        }

        return response()->json(['message' => __('ui.messages.logout')]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()->load('twoFactorSetting')]);
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = PasswordBroker::sendResetLink($data);

        if ($this->wantsJson($request)) {
            return response()->json(['message' => __('ui.messages.reset_sent')], 200);
        }

        return back()->with('status', __('ui.messages.reset_sent'));
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $resetUser = null;
        $status = PasswordBroker::reset(
            $data,
            function (User $user, string $password) use (&$resetUser): void {
                $resetUser = $user;
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            if ($this->wantsJson($request)) {
                return response()->json(['message' => __('ui.messages.reset_invalid')], 422);
            }

            return back()->withErrors(['email' => __('ui.messages.reset_invalid')]);
        }

        if ($resetUser !== null && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $resetUser->getAuthIdentifier())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        if ($this->wantsJson($request)) {
            return response()->json(['message' => __('ui.messages.reset_success')]);
        }

        return redirect()->route('login')->with('status', __('ui.messages.reset_success'));
    }

    private function audit(Request $request, ?User $user, string $action): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
