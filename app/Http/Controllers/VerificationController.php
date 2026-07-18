<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('ui.messages.email_already_verified')]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => __('ui.messages.email_verified')]);
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => __('ui.messages.email_already_verified')]);
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::warning('Verification email could not be sent.', [
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            if (! $this->wantsJson($request)) {
                return back()->withErrors(['email' => __('ui.messages.verification_failed')]);
            }

            return response()->json(['message' => __('ui.messages.verification_failed')], 422);
        }

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.verification_sent'));
        }

        return response()->json(['message' => __('ui.messages.verification_sent')]);
    }
}
