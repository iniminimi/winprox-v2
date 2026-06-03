<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Settings\UpdateUserUiThemeAction;
use App\Enums\UiTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class UiThemeController
{
    public function __invoke(Request $request, string $theme, UpdateUserUiThemeAction $updateUserUiTheme): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $resolved = UiTheme::tryFrom($theme);

        if ($resolved instanceof UiTheme) {
            $updateUserUiTheme->handle($user, $resolved, (int) $user->id);
        }

        return back();
    }
}
