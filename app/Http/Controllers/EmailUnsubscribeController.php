<?php

namespace App\Http\Controllers;

use App\Actions\Contact\ResolveEmailUnsubscribeTokenAction;
use App\Actions\Contact\SetEmailSubscriptionAction;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class EmailUnsubscribeController extends Controller
{
    public function confirm(
        string $token,
        ResolveEmailUnsubscribeTokenAction $resolve,
        SetEmailSubscriptionAction $setSubscription,
    ): View {
        $email = $resolve->handle($token);
        abort_unless(is_string($email) && $email !== '', 403);

        $setSubscription->handle($email, true);

        return $this->unsubscribedView($email);
    }

    public function confirmLegacy(Request $request, SetEmailSubscriptionAction $setSubscription): View
    {
        $email = $this->resolveEmailFromQuery($request);

        $setSubscription->handle($email, true);

        return $this->unsubscribedView($email, (string) $request->query('t'));
    }

    public function resubscribe(Request $request, SetEmailSubscriptionAction $setSubscription): View
    {
        $email = $this->resolveEmailFromQuery($request);

        $setSubscription->handle($email, false);

        return view('email.resubscribed', [
            'email' => $email,
        ]);
    }

    private function unsubscribedView(string $email, ?string $queryToken = null): View
    {
        $resubscribeToken = $queryToken !== null && $queryToken !== ''
            ? $queryToken
            : Crypt::encryptString($email);

        return view('email.unsubscribed', [
            'email' => $email,
            'hasUser' => User::query()->where('email', $email)->exists(),
            'resubscribeUrl' => URL::signedRoute('email.resubscribe', ['t' => $resubscribeToken]),
        ]);
    }

    private function resolveEmailFromQuery(Request $request): string
    {
        $token = $request->query('t');
        abort_unless(is_string($token) && $token !== '', 403);

        try {
            $email = Crypt::decryptString($token);
        } catch (\Throwable) {
            abort(403);
        }

        $email = EmailUnsubscribe::normalizeEmail($email);

        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 403);

        return $email;
    }
}
