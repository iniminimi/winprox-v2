<?php

namespace App\Support\Admin;

use App\Enums\AdminHealthIssueType;

final readonly class AdminHealthIssue
{
    public function __construct(
        public AdminHealthIssueType $type,
        public int $id,
        public string $title,
        public string $subtitle,
        public string $fixUrl,
    ) {}
}
