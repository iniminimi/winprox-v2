<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Actions\Ops\AssignHardwareDeviceTenantAction;
use App\Actions\Ops\BindHardwareDeviceMacAction;
use App\Actions\Ops\EnsureOpsPortalAction;
use App\Actions\Ops\ListHardwareDevicesAction;
use App\Actions\Ops\RegisterHardwareDeviceAction;
use App\Actions\Ops\ResolveHardwareDevicePlacementsAction;
use App\Actions\Ops\UpdateHardwareDeviceAction;
use App\Data\Ops\AssignHardwareDeviceTenantData;
use App\Data\Ops\BindHardwareDeviceMacData;
use App\Data\Ops\RegisterHardwareDeviceData;
use App\Data\Ops\UpdateHardwareDeviceData;
use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceStatus;
use App\Enums\HardwareDeviceType;
use App\Http\Requests\Ops\AssignHardwareDeviceTenantRequest;
use App\Http\Requests\Ops\BindHardwareDeviceMacRequest;
use App\Http\Requests\Ops\RegisterHardwareDeviceRequest;
use App\Http\Requests\Ops\UpdateHardwareDeviceRequest;
use App\Models\HardwareDevice;
use App\Models\OpsPortal;
use App\Models\Tenant;
use App\Support\Ops\HardwareMac;
use App\Support\Qr\QrSvg;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Devices extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $tenantFilter = '';

    #[Url]
    public bool $withoutMac = false;

    public bool $showRegister = false;

    public string $serial_number = '';

    public string $brand = 'Seeed';

    public string $registerType = 'clock_display';

    public string $model = 'Indicator D1';

    public string $sku = '';

    public string $condition = 'new';

    public string $purchase_ref = '';

    public string $notes = '';

    public ?int $editDeviceId = null;

    public string $editBrand = '';

    public string $editType = 'clock_display';

    public string $editModel = '';

    public string $editSku = '';

    public string $editStatus = 'in_stock';

    public string $editCondition = 'new';

    public string $editPurchaseRef = '';

    public string $editNotes = '';

    public string $editMac = '';

    public bool $editMacLocked = false;

    public string $editTenantId = '';

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->authorize('viewAny', HardwareDevice::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedTenantFilter(): void
    {
        $this->resetPage();
    }

    public function updatedWithoutMac(): void
    {
        $this->resetPage();
    }

    public function openRegister(): void
    {
        $this->authorize('create', HardwareDevice::class);
        $this->resetRegisterForm();
        $this->showRegister = true;
    }

    public function closeRegister(): void
    {
        $this->showRegister = false;
        $this->resetRegisterForm();
    }

    public function register(RegisterHardwareDeviceAction $action): void
    {
        $this->authorize('create', HardwareDevice::class);
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $rules = RegisterHardwareDeviceRequest::ruleSet();
        $this->validate([
            'serial_number' => $rules['serial_number'],
            'brand' => $rules['brand'],
            'registerType' => $rules['type'],
            'model' => $rules['model'],
            'sku' => $rules['sku'],
            'condition' => $rules['condition'],
            'purchase_ref' => $rules['purchase_ref'],
            'notes' => $rules['notes'],
        ]);

        try {
            $device = $action->handle(
                RegisterHardwareDeviceData::fromValidated([
                    'serial_number' => $this->serial_number,
                    'brand' => $this->brand,
                    'type' => $this->registerType,
                    'model' => $this->model,
                    'sku' => $this->sku,
                    'condition' => $this->condition,
                    'purchase_ref' => $this->purchase_ref,
                    'notes' => $this->notes,
                ]),
                (int) $user->id,
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('serial_number', __('ops.errors.'.$e->getMessage()));

            return;
        }

        $this->closeRegister();
        $this->flashMessage = __('ops.flash.registered', ['serial' => $device->serial_number]);
        $this->flashType = 'success';
        $this->resetPage();
    }

    public function openEdit(int $deviceId): void
    {
        $device = HardwareDevice::query()->findOrFail($deviceId);
        $this->authorize('update', $device);

        $this->editDeviceId = $device->id;
        $this->editBrand = $device->brand;
        $this->editType = $device->type->value;
        $this->editModel = (string) ($device->model ?? '');
        $this->editSku = (string) ($device->sku ?? '');
        $this->editStatus = $device->status->value;
        $this->editCondition = $device->condition->value;
        $this->editPurchaseRef = (string) ($device->purchase_ref ?? '');
        $this->editNotes = (string) ($device->notes ?? '');
        $this->editMac = $device->mac !== null
            ? (string) (HardwareMac::formatForDisplay($device->mac) ?? $device->mac)
            : '';
        $this->editMacLocked = $device->mac !== null;
        $this->editTenantId = $device->tenant_id !== null ? (string) $device->tenant_id : '';
    }

    public function closeEdit(): void
    {
        $this->editDeviceId = null;
        $this->resetErrorBag();
    }

    public function saveEdit(
        UpdateHardwareDeviceAction $update,
        BindHardwareDeviceMacAction $bindMac,
        AssignHardwareDeviceTenantAction $assignTenant,
    ): void {
        if ($this->editDeviceId === null) {
            return;
        }

        $device = HardwareDevice::query()->findOrFail($this->editDeviceId);
        $this->authorize('update', $device);
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $base = UpdateHardwareDeviceRequest::ruleSet();
        $this->validate([
            'editBrand' => $base['brand'],
            'editType' => $base['type'],
            'editModel' => $base['model'],
            'editSku' => $base['sku'],
            'editStatus' => $base['status'],
            'editCondition' => $base['condition'],
            'editPurchaseRef' => $base['purchase_ref'],
            'editNotes' => $base['notes'],
            'editMac' => ['nullable', 'string', 'max:64'],
            'editTenantId' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        try {
            $update->handle(
                $device,
                UpdateHardwareDeviceData::fromValidated([
                    'brand' => $this->editBrand,
                    'type' => $this->editType,
                    'model' => $this->editModel,
                    'sku' => $this->editSku,
                    'status' => $this->editStatus,
                    'condition' => $this->editCondition,
                    'purchase_ref' => $this->editPurchaseRef,
                    'notes' => $this->editNotes,
                ]),
                (int) $user->id,
            );

            $device->refresh();

            if (trim($this->editMac) !== '' && $device->mac === null) {
                $this->validate(['editMac' => BindHardwareDeviceMacRequest::ruleSet()['mac']]);
                $bindMac->handle(
                    $device,
                    BindHardwareDeviceMacData::fromValidated(['mac' => $this->editMac]),
                    (int) $user->id,
                );
                $device->refresh();
            }

            $assignTenant->handle(
                $device,
                AssignHardwareDeviceTenantData::fromValidated([
                    'tenant_id' => $this->editTenantId !== '' ? (int) $this->editTenantId : null,
                ]),
                (int) $user->id,
            );
        } catch (InvalidArgumentException $e) {
            $key = str_starts_with($e->getMessage(), 'mac_') ? 'editMac' : 'editTenantId';
            $this->addError($key, __('ops.errors.'.$e->getMessage()));

            return;
        }

        $this->closeEdit();
        $this->flashMessage = __('ops.flash.updated');
        $this->flashType = 'success';
    }

    public function render(
        ListHardwareDevicesAction $list,
        EnsureOpsPortalAction $ensurePortal,
        ResolveHardwareDevicePlacementsAction $placements,
    ) {
        $statusEnum = $this->status !== '' ? HardwareDeviceStatus::tryFrom($this->status) : null;
        $typeEnum = $this->type !== '' ? HardwareDeviceType::tryFrom($this->type) : null;
        $withoutTenant = $this->tenantFilter === 'none';
        $tenantId = is_numeric($this->tenantFilter) ? (int) $this->tenantFilter : null;

        $devices = $list->handle(
            search: trim($this->search),
            status: $statusEnum,
            type: $typeEnum,
            tenantId: $withoutTenant ? null : $tenantId,
            withoutTenant: $withoutTenant,
            withoutMac: $this->withoutMac,
        );

        /** @var OpsPortal $portal */
        $portal = $ensurePortal->handle();
        $portalUrl = $portal->portalUrl();

        return view('livewire.platform.devices', [
            'devices' => $devices,
            'placements' => $placements->handle($devices->getCollection()),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => HardwareDeviceStatus::cases(),
            'types' => HardwareDeviceType::cases(),
            'conditions' => HardwareDeviceCondition::cases(),
            'opsPortalUrl' => $portalUrl,
            'opsQrSvg' => QrSvg::svg($portalUrl, 240),
        ]);
    }

    private function resetRegisterForm(): void
    {
        $this->serial_number = '';
        $this->brand = 'Seeed';
        $this->registerType = HardwareDeviceType::ClockDisplay->value;
        $this->model = 'Indicator D1';
        $this->sku = '';
        $this->condition = HardwareDeviceCondition::New->value;
        $this->purchase_ref = '';
        $this->notes = '';
        $this->resetErrorBag();
    }
}
