<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\AssignIssueTeamTaskAction;
use App\Actions\Issues\CreateManagerIssueAction;
use App\Enums\IssueTranslationStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UnitTranslationStatus;
use App\Http\Requests\Issues\AssignIssueTeamTaskRequest;
use App\Http\Requests\Issues\StoreManagerIssueStepOneRequest;
use App\Models\EsgIndicator;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Esg\EsgModuleAccess;
use App\Support\PerStatusListLimit;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    use WithFileUploads;
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'recurring')]
    public bool $recurring = false;

    #[Url(as: 'highlight')]
    public ?int $highlightIssue = null;

    #[Url(as: 'unit_id')]
    public ?int $unitFilter = null;

    #[Url(as: 'create')]
    public bool $openCreate = false;

    #[Url(as: 'limit')]
    public int $perStatusLimit = PerStatusListLimit::DEFAULT;

    public bool $showCreateModal = false;

    public int $createStep = 1;

    public ?int $draftIssueId = null;

    public ?int $location_id = null;

    public ?int $unit_id = null;

    public string $description = '';

    public bool $is_recurring = false;

    public int $recurrence_interval_value = 1;

    public string $recurrence_interval_unit = 'month';

    public int $recurrence_lead_days = 7;

    public ?string $recurrence_first_due_date = null;

    public ?int $esg_indicator_id = null;

    public ?int $internal_team_id = null;

    public ?string $task_note = null;

    public string $task_priority = 'prio_3';

    /** @var array<int, mixed> */
    public array $photos = [];

    public function mount(): void
    {
        $this->perStatusLimit = PerStatusListLimit::normalize($this->perStatusLimit);

        if ($this->highlightIssue === null && session()->has('highlight_issue')) {
            $this->highlightIssue = (int) session()->pull('highlight_issue');
        }

        if ($this->openCreate) {
            $this->openCreate = false;
            $this->openCreateModal();
        }
    }

    public function updatedPerStatusLimit(): void
    {
        $this->perStatusLimit = PerStatusListLimit::normalize($this->perStatusLimit);
    }

    public function approve(int $issue, ApproveIssueAction $approveIssue): void
    {
        $model = Issue::findOrFail($issue);
        $this->authorize('approve', $model);

        $approveIssue->handle($model, auth()->user());
    }

    public function applyFilters(): void
    {
        $this->redirect(route('issues.index', array_filter([
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'team' => $this->teamFilter ?: null,
            'q' => trim($this->search) !== '' ? trim($this->search) : null,
            'recurring' => $this->recurring ? '1' : null,
            'highlight' => $this->highlightIssue ?: null,
            'limit' => $this->perStatusLimit !== PerStatusListLimit::DEFAULT ? $this->perStatusLimit : null,
        ])), navigate: true);
    }

    public function resetFilters(): void
    {
        $this->redirect(route('issues.index', array_filter([
            'highlight' => $this->highlightIssue ?: null,
        ])), navigate: true);
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Issue::class);
        $this->resetCreateForm();
        $this->showCreateModal = true;
        $this->dispatch('wp-prepare-photo-inputs');
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            array_splice($this->photos, $index, 1);
        }
    }

    public function updatedLocationId(): void
    {
        $this->unit_id = null;
    }

    public function updatedUnitId(?int $value): void
    {
        if ($value === null) {
            return;
        }

        $unit = Unit::query()->find($value);

        if ($unit?->category !== null) {
            $firstTeam = $unit->category->teams()->first();
            if ($firstTeam !== null) {
                $this->internal_team_id = (int) $firstTeam->id;
            }
        }
    }

    public function updatedIsRecurring(bool $value): void
    {
        if ($value) {
            $this->recurrence_lead_days = $this->suggestRecurringLeadDays($this->recurrence_interval_unit);
        } else {
            $this->esg_indicator_id = null;
        }
    }

    public function updatedRecurrenceIntervalUnit(string $value): void
    {
        if (! $this->is_recurring) {
            return;
        }

        $this->recurrence_lead_days = $this->suggestRecurringLeadDays($value);
    }

    private function suggestRecurringLeadDays(string $intervalUnit): int
    {
        return match ($intervalUnit) {
            'week' => 2,
            'month' => 7,
            'quarter' => 14,
            default => 30,
        };
    }

    private function prefillTeamFromUnit(): void
    {
        if ($this->internal_team_id !== null || $this->unit_id === null) {
            return;
        }

        $unit = Unit::query()->find($this->unit_id);

        if ($unit?->category !== null) {
            $firstTeam = $unit->category->teams()->first();
            if ($firstTeam !== null) {
                $this->internal_team_id = (int) $firstTeam->id;
            }
        }
    }

    public function saveCreateStepOne(CreateManagerIssueAction $createIssue): void
    {
        $this->authorize('create', Issue::class);

        $this->description = trim($this->description);

        if (blank($this->unit_id)) {
            $this->unit_id = null;
        }
        if (blank($this->location_id)) {
            $this->location_id = null;
        }
        if (blank($this->esg_indicator_id)) {
            $this->esg_indicator_id = null;
        }

        $tenantId = (int) Tenancy::id();
        $validated = $this->validate(
            StoreManagerIssueStepOneRequest::ruleSet($tenantId, EsgModuleAccess::activeTenantHasModule()),
            StoreManagerIssueStepOneRequest::messageSet(),
        );

        $validated['original_language'] = app()->getLocale();

        $issue = $createIssue->handle($validated, auth()->user(), $this->photos);

        $this->draftIssueId = $issue->id;
        $this->prefillTeamFromUnit();
        $this->createStep = 2;
        $this->reset(['photos']);
        $this->dispatch('wp-clear-photo-previews');
    }

    public function saveCreateStepTwo(AssignIssueTeamTaskAction $assignTask): mixed
    {
        $issue = Issue::query()->findOrFail($this->draftIssueId);
        $this->authorize('create', Issue::class);

        $validated = $this->validate(
            AssignIssueTeamTaskRequest::ruleSet(),
            AssignIssueTeamTaskRequest::messageSet(),
        );

        $assignTask->handle(
            $issue,
            (int) $validated['internal_team_id'],
            $validated['task_note'] ?? null,
            TaskPriority::from($validated['task_priority']),
        );

        $this->showCreateModal = false;
        $this->resetCreateForm();

        session()->flash('success', __('issues.create.success'));

        return $this->redirectRoute('issues.index', ['highlight' => $issue->id], navigate: true);
    }

    public function backCreateToStepOne(): void
    {
        $this->createStep = 1;
    }

    private function resetCreateForm(): void
    {
        $this->createStep = 1;
        $this->draftIssueId = null;
        $this->location_id = null;
        $this->unit_id = null;
        $this->description = '';
        $this->is_recurring = false;
        $this->recurrence_interval_value = 1;
        $this->recurrence_interval_unit = 'month';
        $this->recurrence_lead_days = 7;
        $this->recurrence_first_due_date = null;
        $this->esg_indicator_id = null;
        $this->internal_team_id = null;
        $this->task_note = null;
        $this->task_priority = 'prio_3';
        $this->photos = [];
        $this->resetErrorBag();
        $this->dispatch('wp-clear-photo-previews');
    }

    public function render()
    {
        $this->authorize('viewAny', Issue::class);

        $issues = Issue::query()
            ->with(['location', 'unit.translations', 'tasks.team', 'translations'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->statusFilter === '', fn ($q) => $q->where('status', '!=', TaskStatus::Closed))
            ->when($this->teamFilter, fn ($q) => $q->whereHas('tasks', fn ($t) => $t->where('internal_team_id', $this->teamFilter)))
            ->when($this->recurring, fn ($q) => $q->where('is_recurring', true))
            ->when($this->unitFilter, fn ($q) => $q->where('unit_id', $this->unitFilter))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($query) use ($term) {
                    $query->where('description', 'like', $term)
                        ->orWhere('reporter_name', 'like', $term)
                        ->orWhere('reporter_contact', 'like', $term)
                        ->orWhereHas('translations', fn ($translation) => $translation
                            ->where('status', IssueTranslationStatus::Completed)
                            ->where('description', 'like', $term))
                        ->orWhereHas('location', fn ($loc) => $loc->where(function ($locationQuery) use ($term) {
                            $locationQuery->where('name', 'like', $term)
                                ->orWhere('street', 'like', $term)
                                ->orWhere('house_number', 'like', $term)
                                ->orWhere('postal_code', 'like', $term)
                                ->orWhere('city', 'like', $term)
                                ->orWhere('address', 'like', $term);
                        }))
                        ->orWhereHas('unit', fn ($unit) => $unit->where(function ($unitQuery) use ($term) {
                            $unitQuery->where('name', 'like', $term)
                                ->orWhereHas('translations', fn ($translation) => $translation
                                    ->where('status', UnitTranslationStatus::Completed)
                                    ->where(function ($translatedUnit) use ($term) {
                                        $translatedUnit->where('name', 'like', $term)
                                            ->orWhere('description', 'like', $term);
                                    }));
                        }));
                });
            })
            ->orderByDesc('id')
            ->get();

        $pendingIssues = $issues->filter(
            fn ($issue) => ! $issue->isApproved() && $issue->status !== TaskStatus::Closed
        )->values();
        $groupableIssues = $issues->filter(
            fn ($issue) => $issue->isApproved() || $issue->status === TaskStatus::Closed
        );

        $groups = [];
        if ($pendingIssues->isNotEmpty()) {
            $limited = $pendingIssues->take($this->perStatusLimit)->values();
            $groups[] = [
                'kind' => 'pending',
                'title' => __('issues.pending_review'),
                'headModifier' => 'progress',
                'issues' => $limited,
                'shown' => $limited->count(),
                'total' => $pendingIssues->count(),
            ];
        }

        foreach (TaskStatus::cases() as $status) {
            $bucket = $groupableIssues->where('status', $status)->values();
            if ($bucket->isNotEmpty()) {
                $limited = $bucket->take($this->perStatusLimit)->values();
                $groups[] = [
                    'kind' => 'status',
                    'status' => $status,
                    'title' => __($status->labelKey()),
                    'headModifier' => $status->pillModifier(),
                    'issues' => $limited,
                    'shown' => $limited->count(),
                    'total' => $bucket->count(),
                ];
            }
        }

        return view('livewire.issues.index', [
            'groups' => $groups,
            'total' => $issues->count(),
            'perStatusLimits' => PerStatusListLimit::OPTIONS,
            'statuses' => TaskStatus::cases(),
            'teams' => InternalTeam::query()->orderBy('name')->get(),
            'hasFilters' => $this->statusFilter !== '' || $this->teamFilter || $this->search !== '' || $this->recurring || $this->unitFilter,
            'highlightIssue' => $this->highlightIssue,
            'onboarding' => TenantOnboardingState::current(),
            'createLocations' => $this->showCreateModal
                ? Location::query()->orderBy('name')->get()
                : collect(),
            'createUnits' => $this->showCreateModal && $this->location_id
                ? Unit::query()->where('location_id', $this->location_id)->orderBy('name')->get()
                : collect(),
            'createTeams' => $this->showCreateModal
                ? InternalTeam::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'hasEsgModule' => EsgModuleAccess::activeTenantHasModule(),
            'createEsgIndicators' => $this->showCreateModal && EsgModuleAccess::activeTenantHasModule()
                ? EsgIndicator::query()->where('is_active', true)->with('translations')->orderBy('name')->get()
                : collect(),
            'priorities' => TaskPriority::cases(),
        ]);
    }
}
