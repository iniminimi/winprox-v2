<?php

namespace App\Data\Time;

/**
 * Verschil tussen de huidige diensten in het bereik en het herstelpunt.
 *
 * Blokkeren kan niet met een bevestiging worden overschreven.
 * Een waarschuwing eist een expliciete bevestiging vóór het herstel.
 */
final class RosterRestoreAssessment
{
    /**
     * @param  list<array{code: string, worker_id: ?int, date: ?string}>  $blocks
     * @param  list<array{code: string, worker_id: ?int, date: ?string}>  $warnings
     * @param  list<array{type: string, worker_id: int, date: string, planned_shift_id: int, fields: list<string>}>  $differences
     */
    public function __construct(
        public array $blocks,
        public array $warnings,
        public array $differences,
    ) {}

    public function blocked(): bool
    {
        return $this->blocks !== [];
    }

    public function needsConfirmation(): bool
    {
        return ! $this->blocked() && ($this->differences !== [] || $this->warnings !== []);
    }
}
