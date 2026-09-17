<?php

namespace App\Exceptions\Portal;

use RuntimeException;

class FieldSyncException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
        public readonly bool $storeReceipt = true,
    ) {
        parent::__construct($message);
    }
}
