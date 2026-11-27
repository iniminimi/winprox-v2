<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Een beheersessie stopt na twaalf uur, ook als er tussendoor activiteit was.
 */
class EnsureAbsoluteSessionLifetime
{
    public const HOURS = 12;

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $started = $request->session()->get('auth_logged_in_at');
        if (! is_int($started) && ! is_numeric($started)) {
            $request->session()->put('auth_logged_in_at', now()->timestamp);

            return $next($request);
        }

        if (now()->timestamp - (int) $started < self::HOURS * 3600) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.session_expired'));
    }
}
