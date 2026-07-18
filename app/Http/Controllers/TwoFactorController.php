<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function show(Request $request)
    {
        if (! $this->wantsJson($request)) {
            return view('pages.auth.two-factor', ['setting' => $request->user()->twoFactorSetting]);
        }

        return response()->json(['two_factor' => $request->user()->twoFactorSetting]);
    }

    public function enable(Request $request, TwoFactorService $service)
    {
        $user = $request->user();
        $setting = $user->twoFactorSetting()->first();

        if ($setting?->enabled) {
            return $this->failure($request, __('ui.messages.two_factor_already_enabled'), __('ui.messages.two_factor_already_enabled'), 422);
        }

        $code = $request->input('code');
        if ($setting?->secret && $code !== null) {
            $valid = $service->confirmEnable($user, (string) $code);
            if (! $valid) {
                $this->audit($request, $user, 'auth.two_factor_enable_failed');
                throw ValidationException::withMessages(['code' => __('ui.messages.invalid_code')]);
            }

            $this->audit($request, $user, 'auth.two_factor_enabled');
            return $this->success($request, ['message' => __('ui.messages.two_factor_enabled')], 200);
        }

        $result = $service->enable($user);
        $this->audit($request, $user, 'auth.two_factor_setup_started');

        if (! $this->wantsJson($request)) {
            return back()->with('two_factor_setup', $result)->with('status', __('ui.messages.two_factor_setup'));
        }

        return response()->json([
            'message' => __('ui.messages.two_factor_setup'),
            'enabled' => false,
            ...$result,
        ], 201);
    }

    public function disable(Request $request, TwoFactorService $service)
    {
        $data = $request->validate([
            'current_password' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $passwordValid = filled($data['current_password'] ?? null)
            && Hash::check($data['current_password'], $user->getAuthPassword());
        $codeValid = filled($data['code'] ?? null) && $service->verifyTotp($user, $data['code']);

        if (! $passwordValid && ! $codeValid) {
            $this->audit($request, $user, 'auth.two_factor_disable_failed');
            throw ValidationException::withMessages([
                'current_password' => __('ui.messages.password_or_code_required'),
            ]);
        }

        $service->disable($user);
        $this->audit($request, $user, 'auth.two_factor_disabled');

        return $this->success($request, ['message' => __('ui.messages.two_factor_disabled')]);
    }

    public function challenge(Request $request, TwoFactorService $service)
    {
        $pending = $request->session()->get('pending_two_factor');
        if (! is_array($pending) || ! isset($pending['user_id'], $pending['expires_at'])) {
            return $this->noPendingChallenge($request);
        }

        if ((int) $pending['expires_at'] < now()->timestamp) {
            $this->forgetPendingChallenge($request);
            return $this->failure($request, __('ui.messages.challenge_expired'), __('ui.messages.challenge_expired'), 419);
        }

        if ($request->isMethod('get')) {
            if ($this->wantsJson($request)) {
                return response()->json(['message' => __('ui.messages.two_factor_setup')]);
            }
            return view('pages.auth.two-factor-challenge');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);
        $hasCode = filled($data['code'] ?? null);
        $hasRecoveryCode = filled($data['recovery_code'] ?? null);
        if (($hasCode && $hasRecoveryCode) || (! $hasCode && ! $hasRecoveryCode)) {
            throw ValidationException::withMessages(['code' => __('ui.messages.code_or_recovery_required')]);
        }
        $code = $hasCode ? $data['code'] : $data['recovery_code'];

        $user = User::query()->find($pending['user_id']);
        if (! $user) {
            $this->forgetPendingChallenge($request);
            return $this->failure($request, __('ui.messages.challenge_expired'), __('ui.messages.challenge_expired'), 404);
        }

        $result = $service->verifyCode($user, (string) $code);
        if (! $result['valid']) {
            $attempts = ((int) ($pending['attempts'] ?? 0)) + 1;
            if ($attempts >= (int) config('yuejing.two_factor.max_attempts', 5)) {
                $this->forgetPendingChallenge($request);
                $this->audit($request, $user, 'auth.two_factor_challenge_locked');
                return $this->failure($request, __('ui.messages.too_many_attempts'), __('ui.messages.too_many_attempts'), 429);
            }

            $pending['attempts'] = $attempts;
            $request->session()->put('pending_two_factor', $pending);
            $this->audit($request, $user, 'auth.two_factor_challenge_failed');
            throw ValidationException::withMessages(['code' => __('ui.messages.invalid_code_or_recovery')]);
        }

        $remember = (bool) ($pending['remember'] ?? false);
        $this->forgetPendingChallenge($request);
        auth()->login($user, $remember);
        $request->session()->regenerate();
        $this->audit($request, $user, $result['recovery_code'] ? 'auth.two_factor_recovery_used' : 'auth.two_factor_verified');
        $this->audit($request, $user, 'auth.logged_in');

        if (! $this->wantsJson($request)) {
            return redirect()->intended(route('dashboard'))->with('status', __('ui.messages.login_success'));
        }

        return response()->json(['message' => __('ui.messages.login_success'), 'user' => $user]);
    }

    private function noPendingChallenge(Request $request)
    {
        if ($this->wantsJson($request)) {
            return response()->json(['message' => __('ui.messages.challenge_expired')], 404);
        }

        return redirect()->route('login');
    }

    private function forgetPendingChallenge(Request $request): void
    {
        $request->session()->forget(['pending_two_factor', 'pending_two_factor_user_id', 'pending_two_factor_remember']);
    }

    private function success(Request $request, array $payload, int $status = 200)
    {
        if ($this->wantsJson($request)) {
            return response()->json($payload, $status);
        }

        return back()->with('status', $payload['message'] ?? __('ui.messages.operation_success'));
    }

    private function failure(Request $request, string $htmlMessage, string $jsonMessage, int $status)
    {
        if ($this->wantsJson($request)) {
            return response()->json(['message' => $jsonMessage], $status);
        }

        return back()->withErrors(['two_factor' => $htmlMessage]);
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
