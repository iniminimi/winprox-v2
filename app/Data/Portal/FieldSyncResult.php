<?php

namespace App\Data\Portal;

use App\Models\FieldSyncReceipt;

final readonly class FieldSyncResult
{
    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public bool $ok,
        public bool $replayed,
        public int $httpStatus,
        public array $result = [],
        public string $error = '',
        public array $errors = [],
    ) {}

    public static function fromReceipt(FieldSyncReceipt $receipt, bool $replayed): self
    {
        $payload = is_array($receipt->result) ? $receipt->result : [];
        $ok = $receipt->wasSuccessful();

        return new self(
            ok: $ok,
            replayed: $replayed,
            httpStatus: (int) $receipt->http_status,
            result: $ok ? $payload : [],
            error: $ok ? '' : (string) ($payload['error'] ?? __('portal.field_sync.error')),
            errors: $ok ? [] : (is_array($payload['errors'] ?? null) ? $payload['errors'] : []),
        );
    }
}
