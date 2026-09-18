<?php

declare(strict_types=1);

namespace App\Data\Units;

use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

readonly class RecordUnitCheckData
{
    /**
     * @param  list<string>|null  $checklistItems
     * @param  list<UploadedFile>  $photos
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
        public ?string $description = null,
        public array $photos = [],
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
     *     external_id?: string|null,
     *     description?: string|null,
     *     photos?: list<UploadedFile>|null
     * }  $input
     */
    public static function fromValidated(array $input): self
    {
        $latitude = $input['latitude'] ?? null;
        $longitude = $input['longitude'] ?? null;
        $externalId = isset($input['external_id']) ? trim((string) $input['external_id']) : null;
        $description = isset($input['description']) ? trim((string) $input['description']) : null;
        $photos = array_values(array_filter(
            $input['photos'] ?? [],
            static fn ($photo) => $photo instanceof UploadedFile,
        ));

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
            description: $description !== null && $description !== '' ? $description : null,
            photos: $photos,
        );
    }

    public function forTask(?int $taskId, ?int $issueId, bool $withExternalId = true): self
    {
        return new self(
            result: $this->result,
            checkedAt: $this->checkedAt,
            source: $this->source,
            latitude: $this->latitude,
            longitude: $this->longitude,
            taskId: $taskId,
            issueId: $issueId,
            checklistItems: $this->checklistItems,
            externalId: $withExternalId ? $this->externalId : null,
            description: $this->description,
            photos: $this->photos,
        );
    }
}
