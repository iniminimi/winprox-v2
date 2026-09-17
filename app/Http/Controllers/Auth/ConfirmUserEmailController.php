<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConfirmUserEmailByTokenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfirmUserEmailController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ConfirmUserEmailByTokenAction $confirm,
    ): RedirectResponse {
        try {
            $confirm->handle($token);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('auth.verify.link_invalid');

            if ($request->user()) {
                return redirect()
                    ->route('verification.notice')
                    ->with('error', $message);
            }

            return redirect()
                ->route('login')
                ->with('error', $message);
        }

        if ($request->user()) {
            return redirect()
                ->route('dashboard')
                ->with('success', __('auth.verify.confirmed'));
        }

        return redirect()
            ->route('login')
            ->with('status', __('auth.verify.confirmed'));
    }
}
