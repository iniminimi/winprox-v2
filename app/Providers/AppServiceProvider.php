<?php

namespace App\Providers;

use App\Contracts\WebhookEvent;
use App\Listeners\DispatchWebhooksForDomainEvent;
use App\Listeners\RecordAuditLogForDomainEvent;
use App\Support\JsonTranslationLoader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ondersteun per-page JSON-vertaalbestanden: lang/[locale]/[page].json
        $this->app->extend('translation.loader', function ($loader, $app) {
            return new JsonTranslationLoader($app['files'], $app['path.lang']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(WebhookEvent::class, DispatchWebhooksForDomainEvent::class);
        Event::listen(WebhookEvent::class, RecordAuditLogForDomainEvent::class);
    }
}
