<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Actions\Communication\InvalidateUnitTranslationsOnSourceChangeAction;
use App\Actions\Issues\RemoveUnitsFromInspectionRoundsAction;
use App\Actions\QrCodes\StoreQrLinkPhotosAction;
use App\Enums\QrCodeStatus;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class UpdateUnitAction
{
    public function __construct(
        private AuditRecorder $audit,
        private StoreQrLinkPhotosAction $storeQrLinkPhotos,
        private InvalidateUnitTranslationsOnSourceChangeAction $invalidateTranslations,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
        private RemoveUnitsFromInspectionRoundsAction $removeFromRounds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Unit $unit, array $data, ?int $actorUserId = null, array $photos = []): Unit
    {
        $previousName = (string) $unit->name;
        $previousDescription = $unit->description;
        $unitChecksWereAllowed = $unit->allowsUnitChecks();

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

        if (array_key_exists('allow_unit_checks', $data)) {
            $payload['allow_unit_checks'] = (bool) $data['allow_unit_checks'];
        }

        if (array_key_exists('require_reporter_contact', $data)) {
            $payload['require_reporter_contact'] = (bool) $data['require_reporter_contact'];
        }

        if (array_key_exists('require_reporter_email_verification', $data)) {
            $payload['require_reporter_email_verification'] = (bool) $data['require_reporter_email_verification'];
        }

        if (Schema::hasColumn('units', 'category_id')) {
            $payload['category_id'] = $data['category_id'] ?? null;
        }

        if (Schema::hasColumn('units', 'unit_check_list_id')) {
            $payload['unit_check_list_id'] = $data['unit_check_list_id'] ?? null;
        }

        if (Schema::hasColumn('units', 'external_id') && array_key_exists('external_id', $data)) {
            $externalId = trim((string) ($data['external_id'] ?? ''));
            $payload['external_id'] = $externalId !== '' ? $externalId : null;
        }

        $unit->update($payload);

        $fresh = $unit->fresh(['category']);

        if ($unitChecksWereAllowed && ! $fresh->allowsUnitChecks()) {
            $this->removeFromRounds->handle([(int) $fresh->id], (int) $fresh->tenant_id);
        }

        $this->invalidateTranslations->handle($fresh, $previousName, $previousDescription, $actorUserId);
        $this->ensureTranslationSlots->handle($fresh);

        $storedPhotoCount = 0;
        if (! empty($photos)) {
            $activeQr = $fresh->qrCodes()
                ->where('status', QrCodeStatus::Active)
                ->orderBy('id')
                ->first();

            $storedPhotoCount = $this->storeQrLinkPhotos->handle(
                unit: $fresh,
                qrCode: $activeQr,
                photos: $photos,
                actorUserId: $actorUserId,
            );
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
