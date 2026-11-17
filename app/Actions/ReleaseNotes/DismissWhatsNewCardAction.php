<?php

declare(strict_types=1);

namespace App\Actions\ReleaseNotes;

use App\Models\User;

class DismissWhatsNewCardAction
{
    public function __construct(private ListPublishedReleaseNotesAction $notes) {}

    public function handle(User $user): void
    {
        $latest = $this->notes->latest();
        if ($latest === null) {
            return;
        }

        $day = $latest->date->toDateString();
        if ($user->whats_new_dismissed_on?->toDateString() === $day) {
            return;
        }

        $user->forceFill([
            'whats_new_dismissed_on' => $day,
        ])->save();
    }
}
