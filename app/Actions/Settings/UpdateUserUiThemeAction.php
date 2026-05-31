<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\UiTheme;
use App\Models\User;
use InvalidArgumentException;

final class UpdateUserUiThemeAction
{
    public function handle(User $user, UiTheme $theme, int $actorUserId): User
    {
        if ($actorUserId !== (int) $user->id) {
            throw new InvalidArgumentException('UI theme can only be updated for the authenticated user.');
        }

        $user->update(['ui_theme' => $theme->value]);

        return $user->refresh();
    }
}
