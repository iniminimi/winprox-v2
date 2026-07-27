<?php

use App\Http\Middleware\ApplySupportTenantContext;
use App\Support\ResolveAppLocale;
use App\Http\Middleware\AuthenticateIotGateway;
use App\Http\Middleware\CheckApiAccess;
use App\Http\Middleware\EnsureRequestIdempotency;
use App\Http\Middleware\EnsureSuperuser;
use App\Http\Middleware\EnsureTenantHasAppAccess;
use App\Http\Middleware\RequireSupportTenantForSuperuser;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ShareUiTheme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'unit-portal/tasks/sync-status',
        ]);

        $middleware->alias([
            'superuser' => EnsureSuperuser::class,
            'support.tenant' => RequireSupportTenantForSuperuser::class,
            'api.access' => CheckApiAccess::class,
            'iot.gateway' => AuthenticateIotGateway::class,
            'idempotency' => EnsureRequestIdempotency::class,
        ]);

        // Locale-keuze (sessie) toepassen op elke web-request.
        $middleware->web(append: [
            SetLocale::class,
            ShareUiTheme::class,
            ApplySupportTenantContext::class,
            EnsureTenantHasAppAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Foutpagina's renderen vóór web-middleware (bv. onbekende route → 404).
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                ResolveAppLocale::apply($request);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage() ?: 'Forbidden.'], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json([
                'message' => $status === 500 ? 'Server error.' : $e->getMessage(),
            ], $status);
        });
    })->create();
