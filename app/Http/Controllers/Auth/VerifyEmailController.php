<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\MarkUserEmailVerifiedAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(
        EmailVerificationRequest $request,
        MarkUserEmailVerifiedAction $markVerified,
    ): RedirectResponse {
        $user = $request->user();

        if ($user instanceof User) {
            $markVerified->handle($user);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', __('auth.verify.confirmed'));
    }
}
