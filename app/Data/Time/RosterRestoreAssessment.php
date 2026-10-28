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
     * @param  list<array<string, mixed>>  $stand  huidige diensten in het bereik
     */
    public function __construct(
        public array $blocks,
        public array $warnings,
        public array $differences,
        public array $stand = [],
    ) {}

    public function blocked(): bool
    {
        return $this->blocks !== [];
    }

    public function needsConfirmation(): bool
    {
        return ! $this->blocked() && ($this->differences !== [] || $this->warnings !== []);
    }

    /**
     * Vaste vingerafdruk van deze beoordeling. Een bevestiging geldt alleen
     * voor exact deze stand.
     */
    public function token(): string
    {
        $blocks = $this->blocks;
        $warnings = $this->warnings;
        $differences = $this->differences;
        $sort = function (array $items): array {
            usort($items, function (array $a, array $b): int {
                return [
                    (string) ($a['code'] ?? $a['type'] ?? ''),
                    (string) ($a['date'] ?? ''),
                    (int) ($a['worker_id'] ?? 0),
                    (int) ($a['planned_shift_id'] ?? 0),
                ] <=> [
                    (string) ($b['code'] ?? $b['type'] ?? ''),
                    (string) ($b['date'] ?? ''),
                    (int) ($b['worker_id'] ?? 0),
                    (int) ($b['planned_shift_id'] ?? 0),
                ];
            });

            return $items;
        };

        return hash('sha256', json_encode([
            'blocks' => $sort($blocks),
            'warnings' => $sort($warnings),
            'differences' => $sort($differences),
            'stand' => $this->stand,
        ], JSON_THROW_ON_ERROR));
    }
}
