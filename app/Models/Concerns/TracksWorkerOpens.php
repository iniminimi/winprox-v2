<?php

namespace App\Models\Concerns;

/**
 * Wie een document of mededeling opende: alleen uitvoerder-id's, nooit een tijdstip.
 */
trait TracksWorkerOpens
{
    public function initializeTracksWorkerOpens(): void
    {
        $this->mergeCasts([
            'opened_worker_ids' => 'array',
            'is_revised' => 'boolean',
        ]);
    }

    /**
     * @return list<int>
     */
    public function openedWorkerIds(): array
    {
        return array_values(array_map('intval', (array) ($this->opened_worker_ids ?? [])));
    }

    public function isOpenedBy(int $workerId): bool
    {
        return in_array($workerId, $this->openedWorkerIds(), true);
    }

    /**
     * @param  list<int>  $workerIds
     */
    public function openedCountAmong(array $workerIds): int
    {
        return count(array_intersect($this->openedWorkerIds(), array_map('intval', $workerIds)));
    }
}
