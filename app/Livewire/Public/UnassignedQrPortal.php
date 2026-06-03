<?php

namespace App\Livewire\Public;

use App\Actions\QrCodes\LinkQrCodeToUnitAction;
use App\Livewire\Concerns\SwitchesPortalUiTheme;
use App\Models\InternalTeam;
use App\Models\QrCode;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class UnassignedQrPortal extends Component
{
    use SwitchesPortalUiTheme;
    use WithPagination;

    public string $token;
    public QrCode $qrCode;
    public int $tenantId;
    public string $stickerNumber = '';
    public string $email = '';
    public string $password = '';
    public bool $showLogin = true;
    public bool $showSuccess = false;
    public ?int $selectedUnitId = null;
    public string $search = '';
    public string $flashMessage = '';

    public ?Worker $worker = null;
    public ?InternalTeam $team = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->qrCode = QrCode::withoutGlobalScopes()->with('unit')->where('token', $token)->firstOrFail();
        $this->tenantId = $this->qrCode->tenant_id;
        $this->stickerNumber = $this->qrCode->sticker_number;

        Tenancy::actAs($this->tenantId);

        // If QR code is already linked, redirect to unit portal
        if ($this->qrCode->unit_id) {
            $this->redirectRoute('public.unit-portal', ['token' => $this->qrCode->unit->qr_token], navigate: true);
            return;
        }

        // Default to showing login
        $this->showLogin = true;

        // Check for logged in admin/employee
        if (Auth::check() && Auth::user()->can('link', $this->qrCode)) {
            $this->showLogin = false;
            return;
        }

        // Check for logged in team worker
        // First, try to get worker from device cookie without team restriction
        $this->worker = WorkerDeviceSession::workerFromDeviceCookie();
        if ($this->worker) {
            // Verify worker belongs to current tenant
            if ((int) $this->worker->tenant_id === $this->tenantId) {
                // Get the worker's team - load without tenant scoping to ensure we get it
                $this->team = InternalTeam::withoutGlobalScope('tenant')->find($this->worker->internal_team_id);
                if ($this->team && $this->workerCanLinkQrCode()) {
                    $this->showLogin = false;
                    return;
                }
            }
        }
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
    }

    private function workerCanLinkQrCode(): bool
    {
        if (!$this->worker || !$this->team) {
            return false;
        }

        // Worker can link if they belong to the same tenant as the QR code
        return (int) $this->worker->tenant_id === $this->tenantId;
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
            'tenant_id' => $this->tenantId,
        ])) {
            $this->addError('email', __('auth.failed'));
            return;
        }

        $user = Auth::user();

        if (! $user->can('link', $this->qrCode)) {
            Auth::logout();
            $this->addError('email', __('portal.unassigned_qr.no_permission'));
            return;
        }

        // Redirect to refresh component with new session
        $this->redirectRoute('public.unassigned-qr-portal', ['token' => $this->qrCode->token], navigate: true);
    }

    public function link(): void
    {
        $this->validate([
            'selectedUnitId' => 'required|exists:units,id',
        ]);

        $unit = Unit::withoutGlobalScopes()->findOrFail($this->selectedUnitId);

        // Verify unit belongs to current tenant
        if ($unit->tenant_id !== $this->tenantId) {
            $this->addError('selectedUnitId', 'Invalid unit selected');
            return;
        }

        // For team workers, verify unit belongs to their team
        if ($this->worker && $this->team) {
            if ($unit->default_internal_team_id !== $this->team->id) {
                $this->addError('selectedUnitId', 'Unit not assigned to your team');
                return;
            }
        }

        try {
            // Reload QR code to ensure we have the latest state
            $this->qrCode = QrCode::withoutGlobalScopes()->where('token', $this->qrCode->token)->firstOrFail();

            // linked_by must be a user ID (foreign key to users table)
            // Workers are not users, so set to null for worker actions
            $actorId = $this->worker ? null : Auth::id();

            app(LinkQrCodeToUnitAction::class)->handle(
                $this->qrCode,
                $unit,
                $this->tenantId,
                $actorId
            );

            // Redirect to unit portal directly after successful linking
            $this->redirectRoute('public.unit-portal', ['token' => $unit->qr_token], navigate: true);
        } catch (\InvalidArgumentException $e) {
            $this->addError('selectedUnitId', $e->getMessage());
        }
    }

    public function redirectToUnit(): void
    {
        if ($this->qrCode->unit_id) {
            $this->redirectRoute('public.unit-portal', ['token' => $this->qrCode->unit->qr_token], navigate: true);
        }
    }

    public function getUnitsProperty()
    {
        $query = Unit::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true);

        // For team workers, filter by their team AND ensure units belong to correct tenant
        if ($this->worker && $this->team) {
            $query->where('default_internal_team_id', $this->team->id)
                  ->where('tenant_id', $this->tenantId);
        }

        return $query
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->with(['location', 'category', 'defaultInternalTeam', 'qrCodes' => function ($query) {
                $query->where('status', \App\Enums\QrCodeStatus::Active);
            }])
            ->orderBy('name')
            ->paginate(20);
    }

    public function render()
    {
        Tenancy::actAs($this->tenantId);

        return view('livewire.public.unassigned-qr-portal', [
            'qrCode' => $this->qrCode,
            'units' => $this->showLogin ? collect() : $this->units,
            'worker' => $this->worker,
        ]);
    }
}
