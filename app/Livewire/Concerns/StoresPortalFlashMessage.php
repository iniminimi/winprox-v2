<?php

namespace App\Livewire\Concerns;

/**
 * Portal flash messages as translation keys so they follow locale switches.
 */
trait StoresPortalFlashMessage
{
    public string $flashMessage = '';

    public string $flashMessageKey = '';

    /** @var array<string, mixed> */
    public array $flashMessageReplace = [];

    protected function portalFlash(string $key, array $replace = []): void
    {
        $this->flashMessageKey = $key;
        $this->flashMessageReplace = $replace;
        $this->refreshPortalFlash();
    }

    protected function clearPortalFlash(): void
    {
        $this->flashMessage = '';
        $this->flashMessageKey = '';
        $this->flashMessageReplace = [];
    }

    protected function refreshPortalFlash(): void
    {
        if ($this->flashMessageKey === '') {
            $this->flashMessage = '';

            return;
        }

        $this->flashMessage = __($this->flashMessageKey, $this->flashMessageReplace);
    }
}
