<?php

namespace App\Livewire\Concerns;

use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\DeleteWorkerAction;
use App\Http\Requests\Team\StoreWorkerRequest;
use App\Models\InternalTeam;
use App\Models\Worker;

/**
 * Teamleader: workers beheren op het Clock Point-portaal.
 *
 * @phpstan-require-extends \Livewire\Component
 */
trait PortalTeamleaderManageWorkers
{
    public bool $showManageWorkers = false;

    public string $newWorkerFirstName = '';

    public string $newWorkerLastName = '';

    public string $manageWorkersMessage = '';

    public bool $showAddWorkerForm = false;

    public function openManageWorkers(): void
    {
        $this->showManageWorkers = true;
        $this->showAddWorkerForm = true;
        $this->newWorkerFirstName = '';
        $this->newWorkerLastName = '';
        $this->manageWorkersMessage = '';
        $this->resetErrorBag();
    }

    public function closeManageWorkers(): void
    {
        $this->showManageWorkers = false;
        $this->showAddWorkerForm = false;
        $this->newWorkerFirstName = '';
        $this->newWorkerLastName = '';
        $this->manageWorkersMessage = '';
        $this->resetErrorBag();
    }

    public function addWorker(CreateWorkerAction $createWorker): void
    {
        $team = $this->portalManageWorkersTeam();
        $teamleader = $this->portalTeamleaderWorker();
        if ($team === null || $teamleader === null) {
            return;
        }

        $request = new StoreWorkerRequest;
        $validated = $this->validate(
            [
                'newWorkerFirstName' => $request->rules()['first_name'],
                'newWorkerLastName' => $request->rules()['last_name'],
            ],
            [
                'newWorkerFirstName.required' => __('portal.worker.errors.name_required'),
                'newWorkerLastName.required' => __('portal.worker.errors.name_required'),
            ],
        );

        try {
            $createWorker->handle(
                $team,
                ['first_name' => $validated['newWorkerFirstName'], 'last_name' => $validated['newWorkerLastName']],
                null,
                $teamleader,
            );
        } catch (\InvalidArgumentException) {
            return;
        }

        $this->reset(['newWorkerFirstName', 'newWorkerLastName', 'showAddWorkerForm', 'showManageWorkers']);
        $this->resetErrorBag(['newWorkerFirstName', 'newWorkerLastName']);
        $this->portalManageWorkersFlash(__('portal.teamleader.worker_added'));
    }

    public function removeWorker(int $workerId, DeleteWorkerAction $deleteWorker): void
    {
        $team = $this->portalManageWorkersTeam();
        $teamleader = $this->portalTeamleaderWorker();
        if ($team === null || $teamleader === null) {
            return;
        }

        $worker = Worker::query()
            ->where('internal_team_id', $team->id)
            ->whereKey($workerId)
            ->first();

        if ($worker === null) {
            return;
        }

        try {
            $deleteWorker->handle($worker, null, $teamleader);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'cannot_delete_self') {
                $this->portalManageWorkersFlash(__('portal.teamleader.errors.cannot_delete_self'));
            }

            return;
        }

        $this->portalManageWorkersFlash(__('portal.teamleader.worker_deleted', ['name' => $worker->displayName()]));
    }

    abstract protected function portalManageWorkersTeam(): ?InternalTeam;

    abstract protected function portalManageWorkersFlash(string $message): void;
}
