<?php

namespace App\Livewire\Concerns;

use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\DeleteWorkerAction;
use App\Actions\Team\DeleteWorkerPhotoAction;
use App\Actions\Team\UpdateWorkerPhotoAction;
use App\Http\Requests\Team\StoreWorkerRequest;
use App\Models\InternalTeam;
use App\Models\Worker;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

    public ?int $photoTargetWorkerId = null;

    /** @var TemporaryUploadedFile|null */
    public $teamleaderPhoto = null;

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
        $this->photoTargetWorkerId = null;
        $this->teamleaderPhoto = null;
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
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'seat_limit_exceeded') {
                $this->addError('newWorkerFirstName', __('team.errors.seat_limit'));

                return;
            }

            return;
        }

        $this->reset(['newWorkerFirstName', 'newWorkerLastName', 'showAddWorkerForm', 'showManageWorkers']);
        $this->resetErrorBag(['newWorkerFirstName', 'newWorkerLastName']);
        $this->portalManageWorkersFlash('portal.teamleader.worker_added');
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
                $this->portalManageWorkersFlash('portal.teamleader.errors.cannot_delete_self');
            } elseif ($e->getMessage() === 'teamleader_cannot_delete_worker') {
                $this->portalManageWorkersFlash('portal.teamleader.errors.cannot_delete_worker');
            }

            return;
        }

        $this->portalManageWorkersFlash('portal.teamleader.worker_deleted', ['name' => $worker->displayName()]);
    }

    public function startWorkerPhoto(int $workerId): void
    {
        if ($this->portalTeamleaderWorker() === null || $this->teamleaderPhotoWorker($workerId) === null) {
            return;
        }

        $this->photoTargetWorkerId = $workerId;
        $this->teamleaderPhoto = null;
        $this->resetErrorBag(['teamleaderPhoto']);
    }

    public function cancelWorkerPhoto(): void
    {
        $this->reset(['photoTargetWorkerId', 'teamleaderPhoto']);
        $this->resetErrorBag(['teamleaderPhoto']);
    }

    public function updatedTeamleaderPhoto(): void
    {
        $this->saveWorkerPhoto(app(UpdateWorkerPhotoAction::class));
    }

    public function saveWorkerPhoto(UpdateWorkerPhotoAction $updateWorkerPhoto): void
    {
        $teamleader = $this->portalTeamleaderWorker();
        if ($teamleader === null || $this->photoTargetWorkerId === null || $this->teamleaderPhoto === null) {
            return;
        }

        $this->validate(
            ['teamleaderPhoto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048']],
            [
                'teamleaderPhoto.image' => __('team.errors.worker_photo_invalid'),
                'teamleaderPhoto.mimes' => __('team.errors.worker_photo_invalid'),
                'teamleaderPhoto.max' => __('team.errors.worker_photo_max'),
            ],
        );

        $worker = $this->teamleaderPhotoWorker($this->photoTargetWorkerId);
        if ($worker === null) {
            $this->cancelWorkerPhoto();

            return;
        }

        try {
            $updateWorkerPhoto->handle($worker, $this->teamleaderPhoto, null, $teamleader);
        } catch (\InvalidArgumentException) {
            $this->cancelWorkerPhoto();
            $this->portalManageWorkersFlash('portal.teamleader.errors.photo_not_allowed');

            return;
        }

        $this->reset(['photoTargetWorkerId', 'teamleaderPhoto']);
        $this->portalManageWorkersFlash('portal.teamleader.photo_updated', ['name' => $worker->displayName()]);
    }

    public function removeWorkerPhoto(int $workerId, DeleteWorkerPhotoAction $deleteWorkerPhoto): void
    {
        $teamleader = $this->portalTeamleaderWorker();
        $worker = $this->teamleaderPhotoWorker($workerId);
        if ($teamleader === null || $worker === null) {
            return;
        }

        try {
            $deleteWorkerPhoto->handle($worker, null, $teamleader);
        } catch (\InvalidArgumentException) {
            $this->portalManageWorkersFlash('portal.teamleader.errors.photo_not_allowed');

            return;
        }

        $this->portalManageWorkersFlash('portal.teamleader.photo_deleted', ['name' => $worker->displayName()]);
    }

    /** Actieve collega op het eigen team; de teamleader mag ook zichzelf fotograferen. */
    private function teamleaderPhotoWorker(int $workerId): ?Worker
    {
        $team = $this->portalManageWorkersTeam();

        return $team === null
            ? null
            : Worker::query()
                ->where('internal_team_id', $team->id)
                ->where('is_active', true)
                ->whereKey($workerId)
                ->first();
    }

    /** Delete-knop alleen voor workers die deze teamleader zelf net aanmaakte. */
    public function teamleaderCanRemoveWorker(Worker $worker): bool
    {
        $teamleader = $this->portalTeamleaderWorker();

        return $teamleader !== null
            && DeleteWorkerAction::teamleaderCanDelete($worker, $teamleader);
    }

    abstract protected function portalManageWorkersTeam(): ?InternalTeam;

    /** @param  array<string, mixed>  $replace */
    abstract protected function portalManageWorkersFlash(string $key, array $replace = []): void;
}
