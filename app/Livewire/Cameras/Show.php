<?php

declare(strict_types=1);

namespace App\Livewire\Cameras;

use App\Actions\Cameras\ConfirmCameraClaimAction;
use App\Actions\Cameras\DenyCameraClaimAction;
use App\Actions\Cameras\EnsureCameraAction;
use App\Actions\Cameras\IssueCameraPairingCodeAction;
use App\Actions\Cameras\SetCameraForwardingAction;
use App\Actions\Cameras\UnlinkCameraAction;
use App\Enums\CameraClaimStatus;
use App\Models\Camera;
use App\Models\CameraClaim;
use App\Models\CameraPhoto;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithPagination;

    public int $unitId;

    public ?string $pairingCode = null;

    public bool $confirmUnlink = false;

    public ?int $openPhotoId = null;

    public function mount(Unit $unit, EnsureCameraAction $ensure): void
    {
        $this->authorize('view', $unit);
        $this->authorize('create', Camera::class);

        if ((int) $unit->tenant_id !== (int) Tenancy::id()) {
            abort(404);
        }

        $this->unitId = $unit->id;
        $ensure->handle($unit, (int) Tenancy::id());
    }

    public function toggleForwarding(SetCameraForwardingAction $set): void
    {
        $camera = $this->camera();
        $this->authorize('update', $camera);
        $enabled = ! $camera->forwarding_enabled;
        $set->handle($camera, $enabled, (int) Tenancy::id(), auth()->id());
        session()->flash('camera_flash', $enabled ? __('camera.flash.forwarding_on') : __('camera.flash.forwarding_off'));
    }

    public function issueCode(IssueCameraPairingCodeAction $issue): void
    {
        $this->authorize('create', Camera::class);
        $this->pairingCode = $issue->handle($this->unit(), (int) Tenancy::id(), auth()->id());
        session()->flash('camera_flash', __('camera.flash.issued'));
    }

    public function confirmClaim(int $claimId, ConfirmCameraClaimAction $confirm): void
    {
        $claim = $this->claim($claimId);
        $this->authorize('update', $claim->camera);
        $confirm->handle($claim, (int) Tenancy::id(), auth()->id());
        $this->pairingCode = null;
        session()->flash('camera_flash', __('camera.flash.confirmed'));
    }

    public function denyClaim(int $claimId, DenyCameraClaimAction $deny): void
    {
        $claim = $this->claim($claimId);
        $this->authorize('update', $claim->camera);
        $deny->handle($claim, (int) Tenancy::id(), auth()->id());
        session()->flash('camera_flash', __('camera.flash.denied'));
    }

    public function unlink(UnlinkCameraAction $unlink): void
    {
        $camera = $this->camera();
        $this->authorize('update', $camera);
        $unlink->handle($camera, (int) Tenancy::id(), auth()->id());
        $this->confirmUnlink = false;
        $this->pairingCode = null;
        session()->flash('camera_flash', __('camera.flash.unlinked'));
    }

    public function openPhoto(int $photoId): void
    {
        $photo = CameraPhoto::query()->whereKey($photoId)->first();
        if ($photo === null || (int) $photo->camera_id !== (int) $this->camera()->id) {
            return;
        }

        $this->authorize('view', $photo);
        $this->openPhotoId = $photo->id;
    }

    public function closeUnlink(): void
    {
        $this->confirmUnlink = false;
    }

    public function closePhoto(): void
    {
        $this->openPhotoId = null;
    }

    public function render(): \Illuminate\View\View
    {
        $camera = $this->camera();
        $photos = $this->photos($camera);

        return view('livewire.cameras.show', [
            'unit' => $this->unit(),
            'camera' => $camera,
            'pendingClaim' => $camera->claims()->activePending()->latest('id')->first(),
            'photos' => $photos,
            'openPhoto' => $this->openPhotoId === null
                ? null
                : $photos->getCollection()->firstWhere('id', $this->openPhotoId)
                    ?? CameraPhoto::query()->whereKey($this->openPhotoId)->where('camera_id', $camera->id)->first(),
        ]);
    }

    private function unit(): Unit
    {
        return Unit::query()->findOrFail($this->unitId);
    }

    private function camera(): Camera
    {
        $camera = Camera::query()->where('unit_id', $this->unitId)->first();
        abort_if($camera === null, 404);

        return $camera;
    }

    private function claim(int $claimId): CameraClaim
    {
        $claim = CameraClaim::query()
            ->whereKey($claimId)
            ->where('status', CameraClaimStatus::Pending)
            ->first();

        abort_if($claim === null || (int) $claim->camera_id !== (int) $this->camera()->id, 404);

        return $claim;
    }

    /**
     * @return LengthAwarePaginator<int, CameraPhoto>
     */
    private function photos(Camera $camera): LengthAwarePaginator
    {
        return CameraPhoto::query()
            ->where('camera_id', $camera->id)
            ->orderByDesc('id')
            ->paginate(24);
    }
}
