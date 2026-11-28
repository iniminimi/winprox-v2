<?php

namespace App\Http\Middleware;

use App\Support\Http\PhoneUserAgent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beheer (login én ingelogde schermen) opent niet op een smartphone.
 * E-mailbevestiging, uitloggen en een opkuis-link uit de mail blijven bereikbaar.
 */
class EnsureBackofficeDesktop
{
    /** @var list<string> */
    private const ALLOWED_ON_PHONE = [
        'verification.notice',
        'verification.verify',
        'logout',
        'subscription.purge.confirm',
        'login.phone',
        'login.phone.password',
        'login.phone.assert-options',
        'login.phone.assert',
        'login.phone.register-options',
        'login.phone.register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! PhoneUserAgent::isPhone($request->userAgent())) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::ALLOWED_ON_PHONE, true)) {
            return $next($request);
        }

        return response()->view('components.layouts.guest', [
            'title' => __('auth.phone.wall_title').' — WinProx',
            'slot' => new HtmlString(view('auth.phone')->render()),
        ]);
    }
}
