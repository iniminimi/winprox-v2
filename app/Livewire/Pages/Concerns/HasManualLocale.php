<?php

declare(strict_types=1);

namespace App\Livewire\Pages\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\App;
use Livewire\Attributes\Url;

trait HasManualLocale
{
    #[Url(keep: true)]
    public string $lang = '';

    #[Url(as: 'screenshots', except: true, keep: true)]
    public bool $showScreenshots = true;

    protected function mountManualLocale(): void
    {
        $supported = config('locales.supported', []);

        if ($this->lang !== '' && in_array($this->lang, $supported, true)) {
            App::setLocale($this->lang);
        } else {
            $this->lang = App::getLocale();

            if (! in_array($this->lang, $supported, true)) {
                $this->lang = (string) config('locales.default', 'nl');
                App::setLocale($this->lang);
            }
        }
    }

    protected function changeManualLocale(string $locale, string $routeName): void
    {
        if (! in_array($locale, config('locales.supported', []), true)) {
            return;
        }

        $params = ['lang' => $locale];
        if (! $this->showScreenshots) {
            $params['screenshots'] = '0';
        }

        $this->redirect(route($routeName, $params), navigate: false);
    }

    public function toggleManualScreenshots(): void
    {
        $this->showScreenshots = ! $this->showScreenshots;
    }

    protected function manualTenant(): ?Tenant
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $user->loadMissing('tenant');

        return $user->tenant;
    }

    protected function manualTenantName(): string
    {
        return (string) ($this->manualTenant()?->name ?? '');
    }

    protected function manualTenantLogoUrl(): ?string
    {
        return $this->manualTenant()?->logoPublicUrl();
    }
}
