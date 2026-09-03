<?php

namespace App\Livewire\Time;

use App\Actions\Time\RetryPresenceSubmissionAction;
use App\Enums\PresenceSubmissionStatus;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PresenceSubmissionsIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;
    use WithPagination;

    #[Url(as: 'status')]
    public ?string $statusFilter = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PresenceSubmission::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status = ''): void
    {
        $this->statusFilter = $status === '' ? null : $status;
        $this->resetPage();
    }

    public function retry(int $submissionId, RetryPresenceSubmissionAction $retry): void
    {
        $submission = PresenceSubmission::query()
            ->where('tenant_id', (int) Tenancy::id())
            ->whereKey($submissionId)
            ->firstOrFail();

        $this->authorize('retry', $submission);

        try {
            $retry->handle($submission, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            $key = 'time.ciao.errors.'.$e->getMessage();
            $message = __($key);
            $this->addError('retry', $message !== $key
                ? $message
                : __('time.ciao.errors.retry_failed'));

            return;
        }

        session()->flash('time_flash', __('time.ciao.retried'));
    }

    public function render()
    {
        $tenantId = (int) Tenancy::id();
        $tenant = Tenant::query()->find($tenantId);
        $status = PresenceSubmissionStatus::tryFrom((string) $this->statusFilter);

        $query = PresenceSubmission::query()
            ->where('tenant_id', $tenantId)
            ->with(['worker', 'location'])
            ->orderByDesc('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->whereHas('worker', function ($workerQuery) use ($search): void {
                $workerQuery->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%');
            });
        }

        $statusCounts = PresenceSubmission::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('livewire.time.presence-submissions-index', [
            'submissions' => $query->paginate(25),
            'complianceEnabled' => $tenant instanceof Tenant && $tenant->presenceComplianceEnabled(),
            'statusCounts' => $statusCounts,
            'alarmCount' => $this->timeNavAlarmCount(),
            'ciaoFailCount' => $this->timeNavCiaoFailCount(),
            'statusOptions' => PresenceSubmissionStatus::cases(),
        ]);
    }
}
