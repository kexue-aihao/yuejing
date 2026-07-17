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
        $token = e($token);
        $email = e((string) $request->query('email', ''));
        $action = route('password.update');
        $csrf = csrf_token();

        return response(<<<HTML
<!doctype html>
<html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>重置密码</title></head>
<body><main><h1>重置密码</h1><form method="POST" action="{$action}">
<input type="hidden" name="_token" value="{$csrf}"><input type="hidden" name="token" value="{$token}">
<label for="email">邮箱</label><input id="email" name="email" type="email" value="{$email}" required autocomplete="email">
<label for="password">新密码</label><input id="password" name="password" type="password" required autocomplete="new-password">
<label for="password_confirmation">确认新密码</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
<button type="submit">重置密码</button></form></main></body></html>
HTML);
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
                ? '注册成功。请查收验证邮件后继续使用需要验证邮箱的功能。'
                : '注册成功，欢迎来到阅境。');
        }

        return response()->json([
            'message' => 'Registered successfully.',
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
                return back()->withErrors(['email' => '邮箱或密码不正确。'])->withInput();
            }

            return response()->json(['message' => 'Invalid credentials.'], 422);
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
                'message' => 'Two-factor authentication is required.',
                'two_factor_required' => true,
                'challenge_url' => route('two-factor.challenge'),
            ], 202);
        }

        $request->session()->regenerate();
        $this->audit($request, $user, 'auth.logged_in');

        if (! $this->wantsJson($request)) {
            return redirect()->intended(route('dashboard'))->with('status', '登录成功，欢迎回来。');
        }

        return response()->json(['message' => 'Logged in successfully.', 'user' => $user]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->audit($request, $user, 'auth.logged_out');

        if (! $this->wantsJson($request)) {
            return redirect()->route('home')->with('status', '你已安全退出。');
        }

        return response()->json(['message' => 'Logged out successfully.']);
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
            return response()->json(['message' => 'If the account exists, a password reset link has been sent.'], 200);
        }

        return back()->with('status', '如果该邮箱已注册，密码重置链接将发送至你的邮箱。');
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
                return response()->json(['message' => 'The password reset link is invalid or expired.'], 422);
            }

            return back()->withErrors(['email' => '密码重置链接无效或已过期。']);
        }

        if ($resetUser !== null && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $resetUser->getAuthIdentifier())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        if ($this->wantsJson($request)) {
            return response()->json(['message' => 'Password reset successfully.']);
        }

        return redirect()->route('login')->with('status', '密码已重置，请重新登录。');
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
