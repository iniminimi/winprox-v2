<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SetEmailSubscriptionAction;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class EmailUnsubscribeController extends Controller
{
    public function confirm(Request $request, SetEmailSubscriptionAction $setSubscription): View
    {
        $email = $this->resolveEmailFromToken($request);

        $setSubscription->handle($email, true);

        return view('email.unsubscribed', [
            'email' => $email,
            'hasUser' => User::query()->where('email', $email)->exists(),
        ]);
    }

    public function resubscribe(Request $request, SetEmailSubscriptionAction $setSubscription): View
    {
        $email = $this->resolveEmailFromToken($request);

        $setSubscription->handle($email, false);

        return view('email.resubscribed', [
            'email' => $email,
        ]);
    }

    private function resolveEmailFromToken(Request $request): string
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
