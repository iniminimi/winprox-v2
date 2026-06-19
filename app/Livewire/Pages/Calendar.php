<?php

namespace App\Livewire\Pages;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Support\Onboarding\TenantOnboardingState;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Calendar extends Component
{
    #[Url(as: 'view')]
    public string $viewMode = 'month';

    #[Url(as: 'type')]
    public string $entryType = 'tasks';

    #[Url(as: 'date')]
    public string $currentDate = '';

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    public int $dayPage = 1;

    public function mount(): void
    {
        if ($this->currentDate === '') {
            $this->currentDate = now()->toDateString();
        }

        if (! in_array($this->viewMode, ['month', 'week', 'day'], true)) {
            $this->viewMode = 'month';
        }

        if (! in_array($this->entryType, ['tasks', 'issues'], true)) {
            $this->entryType = 'tasks';
        }
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['month', 'week', 'day'], true)) {
            return;
        }

        $this->viewMode = $mode;
        $this->dayPage = 1;
        $current = Carbon::parse($this->currentDate);
        $this->currentDate = match ($mode) {
            'week' => $current->startOfWeek(Carbon::MONDAY)->toDateString(),
            'day' => $current->toDateString(),
            default => $current->startOfMonth()->toDateString(),
        };
    }

    public function setEntryType(string $type): void
    {
        if (in_array($type, ['tasks', 'issues'], true)) {
            $this->entryType = $type;
        }
    }

    public function previousPeriod(): void
    {
        $current = Carbon::parse($this->currentDate);
        $this->currentDate = match ($this->viewMode) {
            'week' => $current->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'day' => $current->subDay()->toDateString(),
            default => $current->subMonthNoOverflow()->startOfMonth()->toDateString(),
        };
    }

    public function nextPeriod(): void
    {
        $current = Carbon::parse($this->currentDate);
        $this->currentDate = match ($this->viewMode) {
            'week' => $current->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'day' => $current->addDay()->toDateString(),
            default => $current->addMonthNoOverflow()->startOfMonth()->toDateString(),
        };
    }

    public function goToToday(): void
    {
        $today = now();
        $this->currentDate = match ($this->viewMode) {
            'week' => $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'day' => $today->toDateString(),
            default => $today->copy()->startOfMonth()->toDateString(),
        };
        $this->dayPage = 1;
    }

    public function nextPage(): void
    {
        $this->dayPage++;
    }

    public function previousPage(): void
    {
        if ($this->dayPage > 1) {
            $this->dayPage--;
        }
    }

    public function render()
    {
        Carbon::setLocale(app()->getLocale());

        $current = Carbon::parse($this->currentDate);
        $isMonthView = $this->viewMode === 'month';
        $isDayView = $this->viewMode === 'day';
        $month = $current->copy()->startOfMonth();

        $gridStart = $isMonthView
            ? $month->copy()->startOfWeek(Carbon::MONDAY)
            : ($isDayView ? $current->copy()->startOfDay() : $current->copy()->startOfWeek(Carbon::MONDAY));

        $gridEnd = $isMonthView
            ? $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)
            : ($isDayView ? $current->copy()->endOfDay() : $current->copy()->endOfWeek(Carbon::SUNDAY));

        $days = collect();
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        $entriesByDate = collect();

        if ($this->entryType === 'issues') {
            $issues = Issue::query()
                ->with(['location', 'unit.translations', 'translations'])
                ->whereBetween('created_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
                ->when($this->locationFilter, fn ($q) => $q->where('location_id', $this->locationFilter))
                ->orderBy('created_at')
                ->get();

            $entriesByDate = $issues->groupBy(fn (Issue $issue) => $issue->created_at?->toDateString());
        } else {
            $tasks = Task::query()
                ->forApprovedIssue()
                ->with(['issue.location', 'issue.unit.translations', 'issue.translations', 'team'])
                ->when($this->locationFilter, fn ($q) => $q->whereHas('issue', fn ($iq) => $iq->where('location_id', $this->locationFilter)))
                ->where(function ($q) use ($gridStart, $gridEnd) {
                    $q->where(function ($sub) use ($gridStart, $gridEnd) {
                        $sub->whereNotNull('scheduled_for')
                            ->whereBetween('scheduled_for', [$gridStart->toDateString(), $gridEnd->toDateString()]);
                    })->orWhere(function ($sub) use ($gridStart, $gridEnd) {
                        $sub->whereNotNull('due_at')
                            ->whereBetween('due_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()]);
                    });
                })
                ->orderByRaw('CASE priority
                    WHEN "prio_1" THEN 1
                    WHEN "prio_2" THEN 2
                    WHEN "prio_3" THEN 3
                    WHEN "prio_4" THEN 4
                    ELSE 5
                END')
                ->orderBy('scheduled_for')
                ->orderBy('due_at')
                ->get();

            $entriesByDate = collect();
            foreach ($tasks as $task) {
                $dateKey = $task->scheduled_for?->toDateString()
                    ?? $task->due_at?->toDateString();
                if ($dateKey === null) {
                    continue;
                }
                $entriesByDate[$dateKey] = ($entriesByDate[$dateKey] ?? collect())->push($task);
            }
        }

        $periodLabel = match ($this->viewMode) {
            'week' => __('calendar.period.week', [
                'start' => $gridStart->isoFormat('D/M'),
                'end' => $gridEnd->isoFormat('D/M'),
            ]),
            'day' => $current->isoFormat('dddd D MMMM YYYY'),
            default => $month->isoFormat('MMMM YYYY'),
        };

        $weekDayLabels = collect(range(0, 6))
            ->map(fn (int $offset) => $gridStart->copy()->startOfWeek(Carbon::MONDAY)->addDays($offset)->isoFormat('dd'));

        return view('livewire.pages.calendar', [
            'days' => $days,
            'entriesByDate' => $entriesByDate,
            'periodLabel' => $periodLabel,
            'weekDayLabels' => $weekDayLabels,
            'currentMonth' => $month,
            'isMonthView' => $isMonthView,
            'isDayView' => $isDayView,
            'locations' => Location::query()->orderBy('name')->get(),
            'dayPage' => $this->dayPage,
            'onboarding' => TenantOnboardingState::current(),
        ]);
    }
}
