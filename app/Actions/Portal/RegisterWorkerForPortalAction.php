<?php

namespace App\Actions\Portal;

use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Portal\WorkerIcon;

class RegisterWorkerForPortalAction
{
    public function __construct(private AttachWorkerDeviceAction $attachDevice) {}
    /**
     * @return array{worker: Worker, device_token: string}
     */
    public function handle(
        InternalTeam $team,
        string $firstName,
        string $lastName,
        string $iconSlug,
    ): array {
        $iconSlug = trim($iconSlug);
        if (! WorkerIcon::isValidSlug($iconSlug)) {
            throw new \InvalidArgumentException('Invalid worker icon slug.');
        }

        $claimable = $this->findClaimableWorkerOnTeam($team, $firstName, $lastName);
        if ($claimable !== null) {
            $claimable->forceFill(['field_icon_slug' => $iconSlug])->save();

            return $this->attachDevice->handle($claimable);
        }

        Tenant::query()->findOrFail($team->tenant_id)->assertCanAddSeats(1);

        $worker = Worker::create([
            'tenant_id' => $team->tenant_id,
            'internal_team_id' => $team->id,
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'field_icon_slug' => $iconSlug,
            'is_active' => true,
        ]);

        return $this->attachDevice->handle($worker);
    }

    private function findClaimableWorkerOnTeam(InternalTeam $team, string $firstName, string $lastName): ?Worker
    {
        $first = mb_strtolower(trim($firstName));
        $last = mb_strtolower(trim($lastName));
        if ($first === '' || $last === '') {
            return null;
        }

        $matches = Worker::where('internal_team_id', $team->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('field_icon_slug')->orWhere('field_icon_slug', ''))
            ->get()
            ->filter(fn (Worker $worker) => mb_strtolower(trim((string) $worker->first_name)) === $first
                && mb_strtolower(trim((string) $worker->last_name)) === $last);

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
