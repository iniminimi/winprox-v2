<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class BriefingPrintController
{
    public function __invoke(Request $request): View
    {
        $tenant = auth()->user()?->tenant;
        $timezone = config('app.timezone');

        $dateInput = $request->query('date');
        $date = is_string($dateInput) && $dateInput !== ''
            ? Carbon::parse($dateInput, $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfDay();

        $teamId = $request->integer('team') ?: null;

        $tasks = Task::query()
            ->with(['issue.location', 'issue.unit', 'team'])
            ->when($teamId, fn ($query) => $query->where('internal_team_id', $teamId))
            ->where(function ($query) use ($date) {
                $query->whereDate('scheduled_for', $date->toDateString())
                    ->orWhereDate('due_at', $date->toDateString());
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

        $filterTeam = $teamId ? $teams->get($teamId) : null;

        return view('briefing.print', [
            'date' => $date,
            'grouped' => $grouped,
            'teams' => $teams,
            'tenant' => $tenant,
            'filterTeam' => $filterTeam,
        ]);
    }
}
