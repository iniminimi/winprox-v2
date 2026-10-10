<?php

namespace App\Livewire\Time;

use App\Actions\Time\CreateClockPointAction;
use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Actions\Time\RenewClockPointQrAction;
use App\Actions\Time\SendClockPointQrMailAction;
use App\Actions\Time\SetClockPointActiveAction;
use App\Actions\Time\UpdateClockPointAction;
use App\Actions\Time\UpdateTenantTimeQrRotationMonthsAction;
use App\Data\Time\SendClockPointQrMailData;
use App\Http\Requests\Time\SendClockPointQrMailRequest;
use App\Http\Requests\Time\StoreClockPointRequest;
use App\Http\Requests\Time\UpdateClockPointRequest;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ClockPointsIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;
    public bool $showQrPackModal = false;
    public ?int $editingClockPointId = null;
    public ?int $qrPackClockPointId = null;
    public string $name = '';
    public ?int $locationId = null;
    public int $sortOrder = 0;
    public ?int $qrRotationMonths = null;
    public ?int $renewQrClockPointId = null;
    public string $qrMailEmail = '';
    public ?string $qrMailFlash = null;

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

        $checkmate = $tenant->checkmateMode();
        $payload = [
            'name' => $validated['name'],
            'location_id' => $checkmate ? null : ($validated['locationId'] ?: null),
            'sort_order' => $checkmate ? 0 : (int) ($validated['sortOrder'] ?? 0),
        ];

        try {
            if ($this->editingClockPointId) {
                $clockPoint = ClockPoint::query()->findOrFail($this->editingClockPointId);
                $this->authorize('update', $clockPoint);
                $update->handle($clockPoint, $payload, auth()->id());
            } else {
                $this->authorize('create', ClockPoint::class);
                $create->handle($tenant, $payload + ['is_active' => true], auth()->id());
            }
        } catch (InvalidArgumentException $e) {
            session()->flash('error', match ($e->getMessage()) {
                'checkmate_single_clock_point' => __('time.clock_points.errors.checkmate_single'),
                default => __('time.clock_points.errors.generic'),
            });

            return;
        }

        $this->showModal = false;
        $this->resetForm();
        session()->flash('time_flash', __('time.clock_points.saved'));
    }

    public function toggleActive(int $clockPointId, SetClockPointActiveAction $setActive): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('update', $clockPoint);

        try {
            $setActive->handle($clockPoint, ! $clockPoint->is_active, auth()->id());
        } catch (InvalidArgumentException $e) {
            session()->flash('error', match ($e->getMessage()) {
                'checkmate_cannot_deactivate_clock_point' => __('time.clock_points.errors.checkmate_cannot_deactivate'),
                default => __('time.clock_points.errors.generic'),
            });
        }
    }

    public function renewQr(int $clockPointId, RenewClockPointQrAction $renew): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('renewQr', $clockPoint);

        $renew->handle($clockPoint, (int) Tenancy::id(), auth()->id());
        session()->flash('time_flash', __('time.clock_points.qr.renewed'));
    }

    public function renewSelectedQr(RenewClockPointQrAction $renew): void
    {
        $this->validate([
            'renewQrClockPointId' => ['required', 'integer'],
        ], [], [
            'renewQrClockPointId' => __('time.clock_points.qr.rotation_renew_clock_point'),
        ]);

        $this->renewQr((int) $this->renewQrClockPointId, $renew);
    }

    public function openQrPackModal(int $clockPointId): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('view', $clockPoint);

        $this->qrPackClockPointId = $clockPoint->id;
        $this->qrMailEmail = '';
        $this->qrMailFlash = null;
        $this->resetErrorBag('qrMailEmail');
        $this->showQrPackModal = true;
    }

    public function closeQrPackModal(): void
    {
        $this->showQrPackModal = false;
        $this->qrPackClockPointId = null;
        $this->qrMailEmail = '';
        $this->qrMailFlash = null;
        $this->resetErrorBag('qrMailEmail');
    }

    public function sendQrMail(SendClockPointQrMailAction $send): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($this->qrPackClockPointId);
        $this->authorize('view', $clockPoint);

        $validated = $this->validate([
            'qrMailEmail' => SendClockPointQrMailRequest::rulesFor()['email'],
        ], [], [
            'qrMailEmail' => __('time.clock_points.qr.email.label'),
        ]);

        $actor = auth()->user();
        $supported = config('locales.supported', []);
        $locale = in_array((string) ($actor?->locale), $supported, true)
            ? (string) $actor->locale
            : (string) app()->getLocale();

        try {
            $send->handle(
                $clockPoint,
                new SendClockPointQrMailData((string) $validated['qrMailEmail']),
                (int) Tenancy::id(),
                $actor?->id,
                $locale,
            );
        } catch (ValidationException $exception) {
            $emailErrors = $exception->errors()['email'] ?? null;
            if ($emailErrors !== null) {
                throw ValidationException::withMessages([
                    'qrMailEmail' => $emailErrors,
                ]);
            }

            throw $exception;
        }

        $this->qrMailEmail = '';
        $this->qrMailFlash = __('time.clock_points.qr.email.sent');
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
        $clockPoints = ClockPoint::query()->with('location')->orderBy('sort_order')->orderBy('name')->get();
        $blockedQrLogs = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'clock_point.qr_blocked')
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('created_at')
            ->get(['model_id', 'payload', 'created_at']);
        if ($this->renewQrClockPointId === null || ! $clockPoints->contains('id', $this->renewQrClockPointId)) {
            $recommended = $clockPoints->first(fn (ClockPoint $clockPoint) => $clockPoint->isRenewalRecommended());
            $this->renewQrClockPointId = $recommended?->id ?? $clockPoints->first()?->id;
        }
        $qrPackClockPoint = $this->qrPackClockPointId !== null
            ? $clockPoints->firstWhere('id', $this->qrPackClockPointId)
            : null;

        return view('livewire.time.clock-points-index', [
            'clockPoints' => $clockPoints,
            // Schermkoppeling (time.clock-displays.*) is buiten de Checkmate-
            // whitelist — de knop hoort dan ook onzichtbaar, geen dode 404.
            'checkmateMode' => CheckmateMode::isActive(Tenant::query()->find($tenantId)),
            'selectedRenewClockPoint' => $clockPoints->firstWhere('id', $this->renewQrClockPointId),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'blockedQrSummary' => $this->summarizeBlockedQrAttempts($blockedQrLogs, $clockPoints),
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

    /**
     * @param  \Illuminate\Support\Collection<int, AuditLog>  $blockedQrLogs
     * @param  \Illuminate\Support\Collection<int, ClockPoint>  $clockPoints
     * @return list<array{name: string, count: int, last_at: \Illuminate\Support\Carbon}>
     */
    private function summarizeBlockedQrAttempts($blockedQrLogs, $clockPoints): array
    {
        return $blockedQrLogs
            ->filter(fn (AuditLog $log) => (($log->payload ?? [])['history_token_id'] ?? null) !== null)
            ->groupBy(fn (AuditLog $log) => (int) (($log->payload ?? [])['clock_point_id'] ?? $log->model_id ?? 0))
            ->map(fn ($logs, $clockPointId) => [
                'name' => $clockPoints->firstWhere('id', (int) $clockPointId)?->name
                    ?? __('time.clock_points.qr.blocked_attempts_unknown_point'),
                'count' => $logs->count(),
                'last_at' => $logs->first()->created_at,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}
