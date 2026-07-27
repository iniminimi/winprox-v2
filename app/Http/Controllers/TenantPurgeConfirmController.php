<?php

namespace App\Http\Controllers;

use App\Actions\TenantPurge\ConfirmTenantPurgeEmailAction;
use App\Models\TenantPurgeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantPurgeConfirmController extends Controller
{
    public function __invoke(
        Request $request,
        TenantPurgeRequest $purgeRequest,
        string $token,
        ConfirmTenantPurgeEmailAction $confirm,
    ): RedirectResponse {
        $user = $request->user();
        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        try {
            $confirm->handle($purgeRequest, $user, $token);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('subscription.purge.errors.generic');

            return redirect()
                ->route('subscription.index')
                ->with('error', $message);
        }

        return redirect()
            ->route('subscription.index')
            ->with('success', __('subscription.purge.email_confirmed'));
    }
}
