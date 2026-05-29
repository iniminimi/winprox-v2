<?php

namespace App\Livewire;

use App\Enums\TaskStatus;
use App\Models\Issue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    public function render()
    {
        $counts = Issue::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [];
        foreach (TaskStatus::cases() as $status) {
            $stats[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return view('livewire.dashboard', [
            'statuses' => TaskStatus::cases(),
            'stats' => $stats,
            'total' => array_sum($stats),
        ]);
    }
}
