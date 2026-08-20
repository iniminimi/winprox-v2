<?php

namespace App\Providers;

use App\Contracts\TranslationSyncRemoteClient;
use App\Contracts\WebhookEvent;
use App\Listeners\AppendEmailUnsubscribeFooterToMessage;
use App\Listeners\BlockUnsubscribedEmailRecipients;
use App\Listeners\DispatchWebhooksForDomainEvent;
use App\Listeners\RecordAuditLogForDomainEvent;
use App\Models\ContactMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ContactMessagePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Services\Translation\OllamaProvider;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\JsonTranslationLoader;
use App\Support\Translation\TranslationSyncRemoteGateway;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\AzureExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

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

        $this->app->singleton(TranslationProviderInterface::class, OllamaProvider::class);
        $this->app->singleton(TranslationSyncRemoteClient::class, TranslationSyncRemoteGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ContactMessage::class, ContactMessagePolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Event::listen(WebhookEvent::class, DispatchWebhooksForDomainEvent::class);
        Event::listen(WebhookEvent::class, RecordAuditLogForDomainEvent::class);

        Event::listen(MessageSending::class, BlockUnsubscribedEmailRecipients::class);
        Event::listen(MessageSending::class, AppendEmailUnsubscribeFooterToMessage::class);

        Event::listen(SocialiteWasCalled::class, [AzureExtendSocialite::class, 'handle']);

        View::prependNamespace('livewire', resource_path('views/vendor/livewire'));

        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.tailwind');

        // Marketingroutes vereisen {locale}; default voor route()-generatie buiten request-context.
        URL::defaults(['locale' => config('locales.default', 'nl')]);
    }
}
