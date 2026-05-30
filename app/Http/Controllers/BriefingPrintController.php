<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

final class BriefingPrintController
{
    public function __invoke(): View
    {
        $tenant = auth()->user()?->tenant;
        $timezone = config('app.timezone');
        $today = Carbon::now($timezone)->toDateString();

        $tasks = Task::query()
            ->with(['issue.location', 'issue.unit', 'team'])
            ->where(function ($query) use ($today) {
                $query->whereDate('scheduled_for', $today)
                    ->orWhereDate('due_at', $today);
            })
            ->whereIn('status', TaskStatus::openValues())
            ->orderBy('internal_team_id')
            ->orderBy('scheduled_for')
            ->get();

        $teams = InternalTeam::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $grouped = $tasks->groupBy(fn (Task $task) => $task->internal_team_id ?? 0);

        return view('briefing.print', [
            'date' => Carbon::now($timezone),
            'grouped' => $grouped,
            'teams' => $teams,
            'tenant' => $tenant,
        ]);
    }
}
