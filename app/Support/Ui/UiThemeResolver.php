<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Enums\UiTheme;
use App\Models\User;

final class UiThemeResolver
{
    public static function resolve(?User $user = null): string
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return UiTheme::default()->value;
        }

        return UiTheme::tryFromString($user->ui_theme)->value;
    }

    /** QR-portalen: sessie (gast/medewerker), anders account-stijl, anders standaard. */
    public static function resolvePortal(): string
    {
        $fromSession = session('ui_theme');

        if (is_string($fromSession) && UiTheme::tryFrom($fromSession) instanceof UiTheme) {
            return $fromSession;
        }

        $user = auth()->user();

        if ($user instanceof User) {
            return UiTheme::tryFromString($user->ui_theme)->value;
        }

        return UiTheme::default()->value;
    }
}
