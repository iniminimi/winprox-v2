<?php

declare(strict_types=1);

namespace App\Livewire\Pages\Concerns;

use Illuminate\Support\Facades\App;
use Livewire\Attributes\Url;

trait HasManualLocale
{
    public array $availableLocales = ['nl', 'fr', 'en', 'de'];

    #[Url(keep: true)]
    public string $lang = '';

    protected function mountManualLocale(): void
    {
        if ($this->lang !== '' && in_array($this->lang, $this->availableLocales, true)) {
            App::setLocale($this->lang);
        } else {
            $this->lang = App::getLocale();
        }
    }

    protected function changeManualLocale(string $locale, string $routeName): void
    {
        if (! in_array($locale, $this->availableLocales, true)) {
            return;
        }

        $this->redirect(route($routeName, ['lang' => $locale]), navigate: false);
    }

    protected function manualTenantName(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return '';
        }

        $user->loadMissing('tenant');

        return (string) ($user->tenant?->name ?? '');
    }
}
