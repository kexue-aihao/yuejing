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
        $required = app(AppSettingService::class)->emailVerificationRequired();

        if ($required && ! $request->user()?->hasVerifiedEmail()) {
            abort(403, __('ui.messages.email_verification_required'));
        }

        return $next($request);
    }
}
