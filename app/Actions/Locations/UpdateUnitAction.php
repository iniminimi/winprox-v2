<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Actions\Communication\InvalidateUnitTranslationsOnSourceChangeAction;
use App\Models\QrLinkPhoto;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class UpdateUnitAction
{
    public function __construct(
        private AuditRecorder $audit,
        private IssuePhotoStorage $storage,
        private InvalidateUnitTranslationsOnSourceChangeAction $invalidateTranslations,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Unit $unit, array $data, ?int $actorUserId = null, array $photos = []): Unit
    {
        $previousName = (string) $unit->name;
        $previousDescription = $unit->description;

        $payload = [
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
        ];

        if (array_key_exists('public_reports_enabled', $data)) {
            $payload['public_reports_enabled'] = (bool) $data['public_reports_enabled'];
        }

        if (array_key_exists('allow_reservations', $data)) {
            $payload['allow_reservations'] = (bool) $data['allow_reservations'];
        }

        if (array_key_exists('require_reporter_contact', $data)) {
            $payload['require_reporter_contact'] = (bool) $data['require_reporter_contact'];
        }

        if (Schema::hasColumn('units', 'category_id')) {
            $payload['category_id'] = $data['category_id'] ?? null;
        }

        if (Schema::hasColumn('units', 'unit_check_list_id')) {
            $payload['unit_check_list_id'] = $data['unit_check_list_id'] ?? null;
        }

        $unit->update($payload);

        $fresh = $unit->fresh();

        $this->invalidateTranslations->handle($fresh, $previousName, $previousDescription, $actorUserId);
        $this->ensureTranslationSlots->handle($fresh);

        $storedPhotoCount = 0;
        if (! empty($photos)) {
            $fresh->loadMissing(['qrCodes' => fn ($q) => $q->where('status', \App\Enums\QrCodeStatus::Active)]);
            $qrCode = $fresh->qrCodes->first();

            if ($qrCode !== null) {
                foreach ($photos as $photo) {
                    if ($photo instanceof UploadedFile) {
                        QrLinkPhoto::create([
                            'tenant_id' => (int) $fresh->tenant_id,
                            'qr_code_id' => (int) $qrCode->id,
                            'unit_id' => (int) $fresh->id,
                            'path' => $this->storage->storePrecompressedCopy($photo),
                        ]);
                        $storedPhotoCount++;
                    }
                }
            }
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'unit.updated',
            modelType: Unit::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'name' => $fresh->name,
                'photo_count' => $storedPhotoCount,
            ],
        );

        return $fresh;
    }
}
