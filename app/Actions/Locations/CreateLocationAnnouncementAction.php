<?php

namespace App\Actions\Locations;

use App\Models\Announcement;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Str;

class CreateLocationAnnouncementAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{body: string, unit_id: ?int, is_active: bool, expires_at: ?string}  $data
     */
    public function handle(Location $location, array $data, int $tenantId, ?int $actorUserId = null): Announcement
    {
        $unitId = $data['unit_id'] ?? null;
        if ($unitId !== null) {
            Unit::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $location->id)
                ->whereKey($unitId)
                ->firstOrFail();
        }

        $body = trim((string) $data['body']);
        $isActive = (bool) ($data['is_active'] ?? true);
        $expiresAt = ! empty($data['expires_at']) ? $data['expires_at'] : null;

        $announcement = Announcement::create([
            'tenant_id' => $tenantId,
            'location_id' => $location->id,
            'unit_id' => $unitId,
            'title' => self::titleFromBody($body),
            'body' => $body,
            'is_active' => $isActive,
            'published_at' => $isActive ? now() : null,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location_announcement.created',
            modelType: Announcement::class,
            modelId: (int) $announcement->id,
            payload: ['id' => $announcement->id, 'location_id' => $location->id],
        );

        return $announcement;
    }

    public static function titleFromBody(string $body): string
    {
        $line = Str::of($body)->before("\n")->trim()->toString();
        if ($line === '') {
            return __('locations.announcements.default_title');
        }

        return Str::limit($line, 120, '');
    }
}
