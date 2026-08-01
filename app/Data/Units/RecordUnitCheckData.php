<?php

declare(strict_types=1);

namespace App\Data\Units;

use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use Carbon\CarbonImmutable;

readonly class RecordUnitCheckData
{
    /**
     * @param  list<string>|null  $checklistItems
     */
    public function __construct(
        public UnitCheckResult $result,
        public CarbonImmutable $checkedAt,
        public UnitCheckSource $source = UnitCheckSource::Portal,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $taskId = null,
        public ?int $issueId = null,
        public ?array $checklistItems = null,
        public ?string $externalId = null,
    ) {}

    /**
     * @param  array{
     *     result: string,
     *     checked_at: string,
     *     source?: string,
     *     latitude?: float|int|string|null,
     *     longitude?: float|int|string|null,
     *     task_id?: int|null,
     *     issue_id?: int|null,
     *     checklist_items?: list<string>|null,
     *     external_id?: string|null
     * }  $input
     */
    public static function fromValidated(array $input): self
    {
        $latitude = $input['latitude'] ?? null;
        $longitude = $input['longitude'] ?? null;
        $externalId = isset($input['external_id']) ? trim((string) $input['external_id']) : null;

        return new self(
            result: UnitCheckResult::from((string) $input['result']),
            checkedAt: CarbonImmutable::parse((string) $input['checked_at']),
            source: UnitCheckSource::from((string) ($input['source'] ?? UnitCheckSource::Portal->value)),
            latitude: $latitude !== null && $latitude !== '' ? (float) $latitude : null,
            longitude: $longitude !== null && $longitude !== '' ? (float) $longitude : null,
            taskId: isset($input['task_id']) ? (int) $input['task_id'] : null,
            issueId: isset($input['issue_id']) ? (int) $input['issue_id'] : null,
            checklistItems: $input['checklist_items'] ?? null,
            externalId: $externalId !== null && $externalId !== '' ? $externalId : null,
        );
    }
}
