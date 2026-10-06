<?php

namespace App\Http\Controllers;

use App\Actions\Briefing\BuildMorningBriefingAction;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final class BriefingPrintController
{
    public function __invoke(Request $request, BuildMorningBriefingAction $build): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant !== null, 403);
        Gate::forUser($user)->authorize('viewAny', Task::class);

        $teamId = $request->integer('team') ?: $request->integer('internal_team_id') ?: null;

        $dateInput = $request->string('date')->toString();
        $date = $dateInput !== ''
            ? Carbon::parse($dateInput, config('app.timezone'))->startOfDay()
            : Carbon::now(config('app.timezone'))->startOfDay();

        $briefing = $build->handle(
            tenant: $user->tenant,
            actor: $user,
            teamId: $teamId,
            date: $date,
            openTasksOnly: $request->boolean('open_tasks'),
        );

        return view('briefing.print', [
            'tenant' => $user->tenant,
            'briefing' => $briefing,
        ]);
    }
}
