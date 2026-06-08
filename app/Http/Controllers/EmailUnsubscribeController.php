<?php

namespace App\Http\Controllers;

use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class EmailUnsubscribeController extends Controller
{
    public function confirm(Request $request): View
    {
        $token = $request->query('t');
        abort_unless(is_string($token) && $token !== '', 404);

        try {
            $email = Crypt::decryptString($token);
        } catch (\Throwable) {
            abort(404);
        }

        $email = EmailUnsubscribe::normalizeEmail($email);

        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 404);

        $row = EmailUnsubscribe::query()->firstOrNew(['email' => $email]);
        $row->unsubscribed_at = now();
        $row->save();

        $matchedUser = User::query()->where('email', $email)->first();

        return view('email.unsubscribed', [
            'email' => $email,
            'hasUser' => $matchedUser !== null,
        ]);
    }
}
