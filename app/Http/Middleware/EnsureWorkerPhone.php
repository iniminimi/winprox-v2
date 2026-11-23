<?php

namespace App\Http\Middleware;

use App\Support\Http\PhoneUserAgent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uitvoerderspagina's (aanmeldlink, prikklok, QR-melding) openen alleen op een smartphone.
 * Een computer of tablet krijgt de melding om de gsm te gebruiken.
 */
class EnsureWorkerPhone
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PhoneUserAgent::isPhone($request->userAgent())) {
            return $next($request);
        }

        return response()->view('components.layouts.public', [
            'title' => __('time.portal.desktop.title').' — WinProx',
            'slot' => new HtmlString(view('livewire.public.time-portal-phone')->render()),
        ]);
    }
}
