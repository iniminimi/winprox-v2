<?php

namespace App\Livewire\Time;

use App\Actions\Time\CountPendingAbsenceRequestsAction;
use App\Actions\Time\DecideAbsenceRequestAction;
use App\Actions\Time\ListAbsenceRequestConflictsAction;
use App\Actions\Time\ListAbsenceRequestsAction;
use App\Actions\Time\WorkerLeaveBalanceAction;
use App\Data\Time\DecideAbsenceRequestData;
use App\Enums\AbsenceRequestStatus;
use App\Http\Requests\Time\DecideAbsenceRequestRequest;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\AbsenceRequest;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;
use App\Support\Validation\TextDescriptionLimits;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class AbsenceRequestsIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    #[Url(as: 'status')]
    public ?string $statusFilter = null;

    #[Url(as: 'photos')]
    public ?string $photosFilter = null;

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'worker')]
    public ?int $workerFilter = null;

    #[Url(as: 'clock_point')]
    public ?int $clockPointFilter = null;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    #[Url(as: 'decide')]
    public ?int $decide = null;

    public ?int $decidingId = null;

    public string $decisionReason = '';

    public function boot(): void
    {
        abort_unless(TimeModuleAccess::rosterEnabledForTenantId((int) Tenancy::id()), 403);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', AbsenceRequest::class);

        if ($this->decide !== null) {
            if ($this->statusFilter === null || $this->statusFilter === '') {
                $this->statusFilter = AbsenceRequestStatus::Pending->value;
            }
            try {
                $this->openDecide((int) $this->decide);
            } catch (ModelNotFoundException) {
                $this->decide = null;
            }
        }
    }

    public function setStatusFilter(string $status = ''): void
    {
        $this->statusFilter = $status === '' ? null : $status;
    }

    public function clearPhotosFilter(): void
    {
        $this->photosFilter = null;
    }

    public function toggleWithPhoto(): void
    {
        $this->photosFilter = $this->photosFilter === 'with' ? null : 'with';
    }

    public function applyFilters(): void
    {
        $this->from = $this->normalizedFilterDate($this->from);
        $this->to = $this->normalizedFilterDate($this->to);
    }

    public function openDecide(int $id): void
    {
        $request = AbsenceRequest::query()->findOrFail($id);
        $this->authorize('decide', $request);
        $this->decidingId = $request->id;
        $this->decisionReason = '';
        $this->resetErrorBag(['decisionReason']);
    }

    public function closeDecide(): void
    {
        $this->decidingId = null;
        $this->decisionReason = '';
        $this->resetErrorBag(['decisionReason']);
    }

    public function approve(DecideAbsenceRequestAction $decide): void
    {
        $this->decide($decide, true);
    }

    public function reject(DecideAbsenceRequestAction $decide): void
    {
        $this->decide($decide, false);
    }

    private function decide(DecideAbsenceRequestAction $decide, bool $approve): void
    {
        $request = AbsenceRequest::query()->findOrFail((int) $this->decidingId);
        $this->authorize('decide', $request);

        $this->validate(
            ['decisionReason' => DecideAbsenceRequestRequest::rulesFor($approve)['reason']],
            [],
            ['decisionReason' => __('time.absence.decision_reason')],
        );

        $tenant = Tenant::query()->findOrFail((int) Tenancy::id());

        try {
            $decide->handle(
                $tenant,
                $request,
                new DecideAbsenceRequestData($approve, $this->decisionReason),
                auth()->user(),
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('decisionReason', __('time.absence.errors.'.$e->getMessage()));

            return;
        }

        session()->flash(
            'time_flash',
            $approve ? __('time.absence.approved') : __('time.absence.rejected'),
        );
        $this->closeDecide();
    }

    private function normalizedFilterDate(string $value): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    public function render(
        ListAbsenceRequestsAction $list,
        ListAbsenceRequestConflictsAction $listConflicts,
        CountPendingAbsenceRequestsAction $countPending,
        WorkerLeaveBalanceAction $leaveBalance,
    ) {
        $tenantId = (int) Tenancy::id();
        $viewer = auth()->user();
        $allowed = $viewer instanceof \App\Models\User ? $viewer->accessibleLocationIds() : null;
        if ($allowed !== null && $this->clockPointFilter !== null) {
            $pointLocation = ClockPoint::query()->whereKey($this->clockPointFilter)->value('location_id');
            if ($pointLocation === null || ! in_array((int) $pointLocation, $allowed, true)) {
                $this->clockPointFilter = null;
            }
        }
        $status = AbsenceRequestStatus::tryFrom((string) $this->statusFilter);
        $expiringPhotos = $this->photosFilter === 'expiring';
        $requests = $list->handle(
            $tenantId,
            $status,
            100,
            $expiringPhotos,
            $this->teamFilter,
            $this->workerFilter,
            $this->normalizedFilterDate($this->from),
            $this->normalizedFilterDate($this->to),
            $this->clockPointFilter,
            $this->photosFilter === 'with',
            auth()->user() instanceof \App\Models\User ? auth()->user() : null,
        );
        $deciding = $this->decidingId !== null
            ? AbsenceRequest::query()->with(['worker', 'shiftType', 'photos'])->find($this->decidingId)
            : null;
        $conflicts = $deciding instanceof AbsenceRequest
            ? $listConflicts->handle($deciding)
            : collect();
        $leaveBalances = $deciding instanceof AbsenceRequest
            ? $leaveBalance->forRequest(Tenant::query()->findOrFail($tenantId), $deciding)
            : [];

        return view('livewire.time.absence-requests-index', [
            'requests' => $requests,
            'deciding' => $deciding,
            'conflicts' => $conflicts,
            'leaveBalances' => $leaveBalances,
            'alarmCount' => $this->timeNavAlarmCount(),
            'pendingCount' => $countPending->handle($tenantId, $allowed),
            'photosFilter' => in_array($this->photosFilter, ['expiring', 'with'], true) ? $this->photosFilter : null,
            'teams' => InternalTeam::query()->with('translations')->orderBy('sort_order')->orderBy('name')->get(),
            'workers' => Worker::query()
                ->where('is_active', true)
                ->when(auth()->user() instanceof \App\Models\User, fn ($query) => $query->visibleTo(auth()->user()))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'clockPoints' => ClockPoint::query()
                ->when($allowed !== null, fn ($query) => $query->whereIn('location_id', $allowed))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'reasonMax' => TextDescriptionLimits::MAX,
        ]);
    }
}
