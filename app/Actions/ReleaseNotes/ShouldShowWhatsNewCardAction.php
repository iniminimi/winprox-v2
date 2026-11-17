<?php

declare(strict_types=1);

namespace App\Actions\ReleaseNotes;

use App\Models\User;
use Carbon\CarbonInterface;

class ShouldShowWhatsNewCardAction
{
    public function __construct(private ListPublishedReleaseNotesAction $notes) {}

    public function handle(User $user, ?CarbonInterface $now = null, ?string $directory = null): bool
    {
        $latest = $this->notes->latest($directory);
        if ($latest === null) {
            return false;
        }

        $zone = (string) config('app.timezone');
        $today = ($now ?? now())->timezone($zone)->startOfDay();
        $noteDay = $latest->date->copy()->timezone($zone)->startOfDay();

        if ($noteDay->lt($today->copy()->subDays(6))) {
            return false;
        }

        $dismissed = $user->whats_new_dismissed_on;
        if ($dismissed !== null && $dismissed->copy()->timezone($zone)->startOfDay()->greaterThanOrEqualTo($noteDay)) {
            return false;
        }

        return true;
    }
}
