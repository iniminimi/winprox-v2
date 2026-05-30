<?php

namespace App\Livewire;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'locations' => Location::query()->count(),
            'units' => Unit::query()->count(),
            'new_issues' => Issue::query()->where('status', TaskStatus::New->value)->count(),
            'open_tasks' => Task::query()->where('status', '!=', TaskStatus::Closed->value)->count(),
        ];

        $recent = Issue::query()
            ->with(['location', 'unit'])
            ->latest()
            ->take(5)
            ->get();

        $tenant = auth()->user()?->tenant;

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'trialDays' => $tenant?->isTrialActive() ? $tenant->trialDaysRemaining() : null,
        ]);
    }
}
