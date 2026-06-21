<?php

namespace App\Livewire\Pages;

use App\Enums\AdminHealthIssueType;
use App\Models\Location;
use App\Support\Admin\AdminHealthIssue;
use App\Support\Admin\AdminHealthReport;
use App\Support\Admin\AdminHealthService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Health extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'type')]
    public string $filter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
    }

    public function render(AdminHealthService $healthService): mixed
    {
        $report = $healthService->report();
        $issues = $this->filteredIssues($report);

        return view('livewire.pages.health', [
            'report' => $report,
            'issues' => $issues,
            'filterOptions' => AdminHealthIssueType::cases(),
        ]);
    }

    /**
     * @return list<AdminHealthIssue>
     */
    private function filteredIssues(AdminHealthReport $report): array
    {
        if ($this->filter === '') {
            return $report->issues;
        }

        $type = AdminHealthIssueType::tryFrom($this->filter);
        if ($type === null) {
            return $report->issues;
        }

        return array_values(array_filter(
            $report->issues,
            static fn (AdminHealthIssue $issue): bool => $issue->type === $type,
        ));
    }
}
