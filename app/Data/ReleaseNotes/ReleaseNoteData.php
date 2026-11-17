<?php

declare(strict_types=1);

namespace App\Data\ReleaseNotes;

use Carbon\CarbonInterface;

final class ReleaseNoteData
{
    /**
     * @param  array<string, list<string>>  $sections
     */
    public function __construct(
        public CarbonInterface $date,
        public array $sections,
    ) {}
}
