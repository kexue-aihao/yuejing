<?php

namespace App\Http\Middleware;

use App\Services\AppSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedIfRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = filter_var(app(AppSettingService::class)->get(
            'email_verification_required',
            config('yuejing.email_verification.required', false),
        ), FILTER_VALIDATE_BOOLEAN);

        if ($required && ! $request->user()?->hasVerifiedEmail()) {
            abort(403, __('ui.messages.email_verification_required'));
        }

        return $next($request);
    }
}
