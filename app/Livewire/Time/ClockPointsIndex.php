<?php

namespace App\Livewire\Time;

use App\Actions\Time\CreateClockPointAction;
use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Actions\Time\RenewClockPointQrAction;
use App\Actions\Time\SetClockPointActiveAction;
use App\Actions\Time\UpdateClockPointAction;
use App\Actions\Time\UpdateTenantTimeQrRotationMonthsAction;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Http\Requests\Time\StoreClockPointRequest;
use App\Http\Requests\Time\UpdateClockPointRequest;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ClockPointsIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    public bool $showModal = false;
    public bool $showQrPackModal = false;
    public ?int $editingClockPointId = null;
    public ?int $qrPackClockPointId = null;
    public string $name = '';
    public ?int $locationId = null;
    public int $sortOrder = 0;
    public ?int $qrRotationMonths = null;

    public function mount(EnsureDefaultClockPointAction $ensureDefaultClockPoint): void
    {
        $this->authorize('viewAny', ClockPoint::class);

        $tenant = Tenant::query()->find(Tenancy::id());
        $this->qrRotationMonths = $tenant?->time_qr_rotation_months
            ?? $tenant?->effectiveTimeQrRotationMonths();

        if ($tenant !== null) {
            $ensureDefaultClockPoint->handle(
                $tenant,
                __('team.clock_point_qr.default_name'),
                auth()->id(),
            );
        }
    }

    public function openCreate(): void
    {
        $this->authorize('create', ClockPoint::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $clockPointId): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('update', $clockPoint);

        $this->editingClockPointId = $clockPoint->id;
        $this->name = $clockPoint->name;
        $this->locationId = $clockPoint->location_id;
        $this->sortOrder = (int) $clockPoint->sort_order;
        $this->showModal = true;
    }

    public function save(CreateClockPointAction $create, UpdateClockPointAction $update): void
    {
        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $rules = $this->editingClockPointId
            ? UpdateClockPointRequest::rulesFor()
            : StoreClockPointRequest::rulesFor();

        $validated = $this->validate([
            'name' => $rules['name'],
            'locationId' => $rules['location_id'],
            'sortOrder' => $rules['sort_order'] ?? ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], [
            'name' => __('time.clock_points.fields.name'),
            'locationId' => __('time.clock_points.fields.location'),
            'sortOrder' => __('time.clock_points.fields.sort_order'),
        ]);

        $payload = [
            'name' => $validated['name'],
            'location_id' => $validated['locationId'] ?: null,
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ];

        if ($this->editingClockPointId) {
            $clockPoint = ClockPoint::query()->findOrFail($this->editingClockPointId);
            $this->authorize('update', $clockPoint);
            $update->handle($clockPoint, $payload, auth()->id());
        } else {
            $this->authorize('create', ClockPoint::class);
            $create->handle($tenant, $payload + ['is_active' => true], auth()->id());
        }

        $this->showModal = false;
        $this->resetForm();
        session()->flash('time_flash', __('time.clock_points.saved'));
    }

    public function toggleActive(int $clockPointId, SetClockPointActiveAction $setActive): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('update', $clockPoint);
        $setActive->handle($clockPoint, ! $clockPoint->is_active, auth()->id());
    }

    public function renewQr(int $clockPointId, RenewClockPointQrAction $renew): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('renewQr', $clockPoint);

        $renew->handle($clockPoint, (int) Tenancy::id(), auth()->id());
        session()->flash('time_flash', __('time.clock_points.qr.renewed'));
    }

    public function openQrPackModal(int $clockPointId): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('view', $clockPoint);

        $this->qrPackClockPointId = $clockPoint->id;
        $this->showQrPackModal = true;
    }

    public function closeQrPackModal(): void
    {
        $this->showQrPackModal = false;
        $this->qrPackClockPointId = null;
    }

    public function saveQrRotationSettings(UpdateTenantTimeQrRotationMonthsAction $update): void
    {
        $this->authorize('create', ClockPoint::class);

        $validated = $this->validate([
            'qrRotationMonths' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [], [
            'qrRotationMonths' => __('time.clock_points.qr.rotation_months'),
        ]);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $months = $validated['qrRotationMonths'];
        $update->handle($tenant, $months !== null ? (int) $months : null, auth()->id());
        session()->flash('time_flash', __('time.clock_points.qr.rotation_saved'));
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $tenantId = (int) Tenancy::id();
        $qrPackClockPoint = $this->qrPackClockPointId !== null
            ? ClockPoint::query()->with('location')->find($this->qrPackClockPointId)
            : null;

        return view('livewire.time.clock-points-index', [
            'clockPoints' => ClockPoint::query()->with('location')->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'blockedQrAttempts' => AuditLog::query()
                ->where('tenant_id', $tenantId)
                ->where('action', 'clock_point.qr_blocked')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'alarmCount' => $this->timeNavAlarmCount(),
            'qrPackClockPoint' => $qrPackClockPoint,
            'qrPackTemplates' => QrStickerSheetTemplate::printableDownloadCases(),
        ]);
    }

    private function resetForm(): void
    {
        $this->editingClockPointId = null;
        $this->name = '';
        $this->locationId = null;
        $this->sortOrder = 0;
        $this->resetErrorBag();
    }
}
