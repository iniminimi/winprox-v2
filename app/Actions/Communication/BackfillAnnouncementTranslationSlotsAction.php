<?php

namespace App\Actions\Communication;

use App\Models\Announcement;
use App\Models\AnnouncementTranslation;

class BackfillAnnouncementTranslationSlotsAction
{
    public function __construct(private EnsureAnnouncementTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{announcements: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $announcementsProcessed = 0;
        $slotsCreated = 0;

        Announcement::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($announcements) use (&$announcementsProcessed, &$slotsCreated): void {
                foreach ($announcements as $announcement) {
                    $before = AnnouncementTranslation::query()
                        ->where('announcement_id', $announcement->id)
                        ->count();

                    $this->ensureSlots->handle($announcement);

                    $after = AnnouncementTranslation::query()
                        ->where('announcement_id', $announcement->id)
                        ->count();

                    $announcementsProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'announcements' => $announcementsProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
