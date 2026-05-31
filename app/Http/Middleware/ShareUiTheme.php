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
        View::share('uiTheme', UiThemeResolver::resolve($request->user()));

        return $next($request);
    }
}
