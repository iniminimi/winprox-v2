<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Marketing\MarketingDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Houdt een marktdomein bij de publieke pagina's. App-paden gaan naar winprox.app.
 */
class ConfineMarketHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = MarketingDomain::gate($request);

        if ($gate['action'] === 'missing') {
            abort(404);
        }

        if ($gate['action'] === 'redirect') {
            $response = redirect()->away($gate['url'], $gate['status']);
            $response->headers->set('Vary', 'Host', false);

            return $response;
        }

        $response = $next($request);
        $response->headers->set('Vary', 'Host', false);

        return $response;
    }
}
