<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Ui\UiThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class ShareUiTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $theme = $request->routeIs(
            'public.unit-portal',
            'public.location-portal',
            'public.time-portal',
        )
            ? UiThemeResolver::resolvePortal()
            : UiThemeResolver::resolve($request->user());

        View::share('uiTheme', $theme);

        return $next($request);
    }
}
