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
}
