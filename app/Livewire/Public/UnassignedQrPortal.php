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
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class UnassignedQrPortal extends Component
{
    use SwitchesPortalUiTheme;
    use WithFileUploads;
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

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    // GPS capture
    public ?Unit $linkedUnit = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public bool $showGpsCapture = false;
    public bool $gpsCaptureSuccess = false;

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

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            array_splice($this->photos, $index, 1);
        }
    }

    public function link(): void
    {
        $this->validate([
            'selectedUnitId' => 'required|exists:units,id',
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'max:10240'],
        ], [
            'photos.max' => __('portal.report.errors.photos_max'),
            'photos.*.image' => __('portal.report.errors.photos_image'),
            'photos.*.max' => __('portal.report.errors.photos_size'),
        ]);

        $unit = Unit::withoutGlobalScopes()->findOrFail($this->selectedUnitId);

        // Verify unit belongs to current tenant
        if ($unit->tenant_id !== $this->tenantId) {
            $this->addError('selectedUnitId', 'Invalid unit selected');
            return;
        }

        // For team workers, verify unit belongs to their team via category
        if ($this->worker && $this->team) {
            if ($unit->category === null) {
                $this->addError('selectedUnitId', 'Unit has no category');
                return;
            }

            $categoryTeamIds = $unit->category->teams()->pluck('internal_teams.id')->toArray();
            if (!in_array($this->team->id, $categoryTeamIds, true)) {
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
                $actorId,
                $this->photos,
            );

            $this->reset('photos');
            $this->dispatch('wp-clear-photo-previews');

            $this->showSuccess = true;

            // Check if GPS capture is needed
            $unit->refresh();
            $hasGps = $unit->hasGps();

            \Illuminate\Support\Facades\Log::debug('UnassignedQrPortal: Checking GPS status', [
                'unit_id' => $unit->id,
                'has_gps' => $hasGps,
                'latitude' => $unit->latitude,
                'longitude' => $unit->longitude,
            ]);

            if (! $hasGps) {
                $this->linkedUnit = $unit;
                $this->showGpsCapture = true;
                // Don't redirect yet - show GPS capture first
                return;
            }

            // Redirect to unit portal directly after successful linking (with GPS)
            $this->redirectRoute('public.unit-portal', ['token' => $unit->qr_token], navigate: true);
        } catch (\InvalidArgumentException $e) {
            $this->addError('selectedUnitId', $e->getMessage());
        }
    }

    public function saveGps(): void
    {
        if ($this->latitude === null || $this->longitude === null) {
            $this->addError('gps', __('qr.connect.gps_validation_required'));
            return;
        }

        if (! $this->linkedUnit) {
            return;
        }

        // Determine actor ID - workers don't have user IDs
        $actorId = $this->worker ? null : Auth::id();

        app(\App\Actions\Units\UpdateUnitGpsAction::class)->handle(
            $this->linkedUnit,
            $this->latitude,
            $this->longitude,
            $this->tenantId,
            $actorId
        );

        // Redirect to unit portal after saving GPS
        $this->redirectRoute('public.unit-portal', ['token' => $this->linkedUnit->qr_token], navigate: true);
    }

    public function skipGps(): void
    {
        $this->showGpsCapture = false;
        // Redirect to unit portal without GPS
        if ($this->linkedUnit) {
            $this->redirectRoute('public.unit-portal', ['token' => $this->linkedUnit->qr_token], navigate: true);
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

        // For team workers, filter by their team via category
        if ($this->worker && $this->team) {
            $query->whereHas('category', function ($q) {
                $q->whereHas('teams', function ($teamQuery) {
                    $teamQuery->where('internal_teams.id', $this->team->id);
                });
            });
        }

        return $query
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->with(['location', 'category', 'qrCodes' => function ($query) {
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
