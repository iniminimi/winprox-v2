<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\UiTheme;

trait SwitchesPortalUiTheme
{
    public function switchUiTheme(string $theme): void
    {
        $resolved = UiTheme::tryFrom($theme);

        if (! $resolved instanceof UiTheme) {
            return;
        }

        session(['ui_theme' => $resolved->value]);
        $this->dispatch('ui-theme-changed', theme: $resolved->value);
    }
}
