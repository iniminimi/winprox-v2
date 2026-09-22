<?php

namespace App\Livewire\Time;

use App\Actions\Time\CountPendingAbsenceRequestsAction;
use App\Actions\Time\DecideAbsenceRequestAction;
use App\Actions\Time\ListAbsenceRequestConflictsAction;
use App\Actions\Time\ListAbsenceRequestsAction;
use App\Data\Time\DecideAbsenceRequestData;
use App\Enums\AbsenceRequestStatus;
use App\Http\Requests\Time\DecideAbsenceRequestRequest;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\AbsenceRequest;
use App\Models\Tenant;
use App\Support\Tenancy;
use App\Support\Validation\TextDescriptionLimits;
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

    public ?int $decidingId = null;

    public string $decisionReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AbsenceRequest::class);
    }

    public function setStatusFilter(string $status = ''): void
    {
        $this->statusFilter = $status === '' ? null : $status;
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
            ['decisionReason' => DecideAbsenceRequestRequest::rulesFor()['reason']],
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

    public function render(
        ListAbsenceRequestsAction $list,
        ListAbsenceRequestConflictsAction $listConflicts,
        CountPendingAbsenceRequestsAction $countPending,
    ) {
        $tenantId = (int) Tenancy::id();
        $status = AbsenceRequestStatus::tryFrom((string) $this->statusFilter);
        $requests = $list->handle($tenantId, $status);
        $deciding = $this->decidingId !== null
            ? AbsenceRequest::query()->with(['worker', 'shiftType'])->find($this->decidingId)
            : null;
        $conflicts = $deciding instanceof AbsenceRequest
            ? $listConflicts->handle($deciding)
            : collect();

        return view('livewire.time.absence-requests-index', [
            'requests' => $requests,
            'deciding' => $deciding,
            'conflicts' => $conflicts,
            'alarmCount' => $this->timeNavAlarmCount(),
            'pendingCount' => $countPending->handle($tenantId),
            'reasonMax' => TextDescriptionLimits::MAX,
        ]);
    }
}
