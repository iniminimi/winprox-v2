<?php

declare(strict_types=1);

namespace App\Data\Iot;

use App\Enums\IotEventKind;
use Carbon\CarbonImmutable;

readonly class IngestIotEventData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $externalSensorId,
        public IotEventKind $kind,
        public CarbonImmutable $occurredAt,
        public ?float $value = null,
        public ?string $idempotencyKey = null,
        public array $rawPayload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(
            externalSensorId: (string) $input['external_sensor_id'],
            kind: IotEventKind::from((string) $input['kind']),
            occurredAt: CarbonImmutable::parse((string) $input['occurred_at']),
            value: array_key_exists('value', $input) && $input['value'] !== null && $input['value'] !== ''
                ? (float) $input['value']
                : null,
            idempotencyKey: filled($input['idempotency_key'] ?? null)
                ? (string) $input['idempotency_key']
                : null,
            rawPayload: $input,
        );
    }
}
