<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Actions\UnitMeasurements\SyncUnitMeasureFieldsAction;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitBulkBatch;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class BulkCreateUnitsAction
{
    public const MAX_UNITS = 500;

    public const SCHEME_RANGES = 'ranges';

    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
        private SyncUnitMeasureFieldsAction $syncMeasureFields,
    ) {}

    /**
     * Soft preview for UI: skips incomplete rows.
     *
     * @param  list<array<string, mixed>>  $ranges
     * @return array{
     *     names: list<string>,
     *     duplicates: list<string>,
     *     total: int,
     *     truncated: bool,
     *     preview_names: list<string>
     * }
     */
    public function preview(array $ranges): array
    {
        $usable = [];

        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $start = trim((string) ($range['start'] ?? ''));
            if ($start === '' || preg_match('/^\d+$/', $start) !== 1) {
                continue;
            }

            $count = (int) ($range['count'] ?? 0);
            if ($count < 1) {
                continue;
            }

            $paddingRaw = $range['padding'] ?? null;
            if ($paddingRaw !== null && $paddingRaw !== '' && (int) $paddingRaw < strlen($start)) {
                continue;
            }

            $usable[] = [
                'start' => $start,
                'count' => $count,
                'padding' => $paddingRaw,
                'prefix' => (string) ($range['prefix'] ?? ''),
                'suffix' => (string) ($range['suffix'] ?? ''),
            ];
        }

        if ($usable === []) {
            return [
                'names' => [],
                'duplicates' => [],
                'total' => 0,
                'truncated' => false,
                'preview_names' => [],
            ];
        }

        $totalCount = array_sum(array_map(fn (array $r): int => (int) $r['count'], $usable));
        if ($totalCount > self::MAX_UNITS) {
            return [
                'names' => [],
                'duplicates' => [],
                'total' => 0,
                'truncated' => false,
                'preview_names' => [],
            ];
        }

        $names = $this->namesFromRanges($usable);
        $duplicates = $this->duplicateNames($names);
        $total = count($names);
        $limit = 16;

        if ($total <= $limit) {
            return [
                'names' => $names,
                'duplicates' => $duplicates,
                'total' => $total,
                'truncated' => false,
                'preview_names' => $names,
            ];
        }

        return [
            'names' => $names,
            'duplicates' => $duplicates,
            'total' => $total,
            'truncated' => true,
            'preview_names' => [
                ...array_slice($names, 0, $limit - 1),
                $names[$total - 1],
            ],
        ];
    }

    /**
     * Expand validated ranges into unit names (start .. start+count-1, padded).
     *
     * @param  list<array<string, mixed>>  $ranges
     * @return list<string>
     */
    public function namesFromRanges(array $ranges): array
    {
        $names = [];

        foreach ($ranges as $range) {
            $startStr = trim((string) ($range['start'] ?? ''));
            $count = (int) ($range['count'] ?? 0);
            $paddingRaw = $range['padding'] ?? null;
            $padding = ($paddingRaw === null || $paddingRaw === '')
                ? strlen($startStr)
                : max((int) $paddingRaw, strlen($startStr));
            $prefix = (string) ($range['prefix'] ?? '');
            $suffix = (string) ($range['suffix'] ?? '');
            $start = (int) $startStr;

            for ($i = 0; $i < $count; $i++) {
                $number = str_pad((string) ($start + $i), $padding, '0', STR_PAD_LEFT);
                $names[] = $prefix.$number.$suffix;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    public function duplicateNames(array $names): array
    {
        $counts = array_count_values($names);
        $duplicates = [];

        foreach ($counts as $name => $count) {
            if ($count > 1) {
                $duplicates[] = (string) $name;
            }
        }

        return $duplicates;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{batch: UnitBulkBatch, created: int}
     */
    public function handle(Location $location, array $data, int $tenantId, ?int $actorUserId = null): array
    {
        /** @var list<array<string, mixed>> $ranges */
        $ranges = array_values($data['ranges'] ?? []);

        $names = $this->namesFromRanges($ranges);

        if ($names === []) {
            throw new InvalidArgumentException('invalid');
        }

        if ($this->duplicateNames($names) !== []) {
            throw new InvalidArgumentException('duplicates');
        }

        if (count($names) > self::MAX_UNITS) {
            throw new InvalidArgumentException('too_many');
        }

        $existingNames = Unit::query()
            ->where('location_id', $location->id)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $names = array_values(array_diff($names, $existingNames));
        $total = count($names);

        if ($total === 0) {
            throw new InvalidArgumentException('names_exist');
        }

        Tenant::query()->findOrFail($tenantId)->assertCanAddUnits($total);

        $categoryId = isset($data['category_id']) && $data['category_id'] !== ''
            ? (int) $data['category_id']
            : null;

        $batchPrefix = null;
        foreach ($ranges as $range) {
            $prefix = trim((string) ($range['prefix'] ?? ''));
            if ($prefix !== '') {
                $batchPrefix = $prefix;
                break;
            }
        }

        $rangeCount = count($ranges);

        $allowUnitChecks = array_key_exists('allow_unit_checks', $data)
            ? (bool) $data['allow_unit_checks']
            : false;
        $allowUnitMeasurements = array_key_exists('allow_unit_measurements', $data)
            ? (bool) $data['allow_unit_measurements']
            : false;

        $portal = [
            'public_reports_enabled' => array_key_exists('public_reports_enabled', $data)
                ? (bool) $data['public_reports_enabled']
                : true,
            'allow_reservations' => array_key_exists('allow_reservations', $data)
                ? (bool) $data['allow_reservations']
                : false,
            'allow_unit_checks' => $allowUnitChecks,
            'allow_unit_measurements' => $allowUnitMeasurements,
            'require_reporter_contact' => array_key_exists('require_reporter_contact', $data)
                ? (bool) $data['require_reporter_contact']
                : false,
            'require_reporter_email_verification' => array_key_exists('require_reporter_email_verification', $data)
                ? (bool) $data['require_reporter_email_verification']
                : false,
        ];

        if (Schema::hasColumn('units', 'unit_check_list_id')) {
            $portal['unit_check_list_id'] = $allowUnitChecks
                ? ($data['unit_check_list_id'] ?? null)
                : null;
        }

        if (Schema::hasColumn('units', 'latitude') && array_key_exists('latitude', $data)) {
            $portal['latitude'] = self::nullableCoord($data['latitude'] ?? null, -90, 90);
        }

        if (Schema::hasColumn('units', 'longitude') && array_key_exists('longitude', $data)) {
            $portal['longitude'] = self::nullableCoord($data['longitude'] ?? null, -180, 180);
        }

        $measureFieldIds = [];
        if ($allowUnitMeasurements && array_key_exists('measure_field_ids', $data) && is_array($data['measure_field_ids'])) {
            $measureFieldIds = array_values(array_unique(array_map('intval', $data['measure_field_ids'])));
        }

        return DB::transaction(function () use (
            $location,
            $tenantId,
            $names,
            $total,
            $batchPrefix,
            $rangeCount,
            $categoryId,
            $actorUserId,
            $data,
            $portal,
            $measureFieldIds,
        ): array {
            $batch = UnitBulkBatch::create([
                'tenant_id' => $tenantId,
                'location_id' => $location->id,
                'prefix' => $batchPrefix,
                'scheme' => self::SCHEME_RANGES,
                'floors' => $rangeCount,
                'rooms_per_floor' => $total,
                'units_count' => $total,
            ]);

            foreach ($names as $name) {
                $unit = Unit::create([
                    'tenant_id' => $tenantId,
                    'location_id' => $location->id,
                    'bulk_batch_id' => $batch->id,
                    'name' => $name,
                    'category_id' => $categoryId,
                    'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
                    'is_active' => true,
                    ...$portal,
                ]);

                $this->ensureTranslationSlots->handle($unit);

                if ($measureFieldIds !== []) {
                    $this->syncMeasureFields->handle($unit, $measureFieldIds, $actorUserId);
                }
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'unit_bulk.created',
                modelType: UnitBulkBatch::class,
                modelId: (int) $batch->id,
                payload: ['id' => $batch->id, 'created' => $total, 'location_id' => $location->id],
            );

            return ['batch' => $batch, 'created' => $total];
        });
    }

    private static function nullableCoord(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $n = (float) $value;
        if ($n < $min || $n > $max) {
            return null;
        }

        return $n;
    }
}
