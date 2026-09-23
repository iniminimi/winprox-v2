<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Data\Dashboard\DashboardIntentHubData;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;

class BuildDashboardIntentHubAction
{
    public function handle(Tenant $tenant, User $user, ?CarbonInterface $now = null): DashboardIntentHubData
    {
        $now ??= now();
        $name = $this->displayName($user);
        $greetingKey = $this->greetingKey($now);

        $tiles = [];

        if ($tenant->hasTimeModule()) {
            $tiles[] = [
                'key' => 'roster',
                'icon' => 'calendar',
                'tone' => 'new_issues',
                'title' => 'dashboard.intent.tiles.roster.title',
                'body' => 'dashboard.intent.tiles.roster.body',
                'href' => route('time.schedule.index'),
            ];

            $tiles[] = [
                'key' => 'presence',
                'icon' => 'clock',
                'tone' => 'units',
                'title' => 'dashboard.intent.tiles.presence.title',
                'body' => 'dashboard.intent.tiles.presence.body',
                'href' => route('time.presence.index'),
            ];
        }

        if ($user->can('createInspectionRound', Issue::class)) {
            $tiles[] = [
                'key' => 'day_task',
                'icon' => 'tasks',
                'tone' => 'locations',
                'title' => 'dashboard.intent.tiles.day_task.title',
                'body' => 'dashboard.intent.tiles.day_task.body',
                'href' => route('issues.index', [
                    'recurring' => 1,
                    'inspection_round' => 1,
                    'round_create' => 1,
                ]),
            ];
        }

        if ($user->can('create', Location::class)) {
            $tiles[] = [
                'key' => 'location',
                'icon' => 'locations',
                'tone' => 'present_now',
                'title' => 'dashboard.intent.tiles.location.title',
                'body' => 'dashboard.intent.tiles.location.body',
                'href' => route('locations.index', ['create' => 1]),
            ];
        }

        if ($this->canAddWorker($user)) {
            $tiles[] = [
                'key' => 'worker',
                'icon' => 'team',
                'tone' => 'open_tasks',
                'title' => 'dashboard.intent.tiles.worker.title',
                'body' => 'dashboard.intent.tiles.worker.body',
                'href' => route('team.index', [
                    'section' => 'teams',
                    'create_worker' => 1,
                ]),
            ];
        }

        return new DashboardIntentHubData(
            greeting: __($greetingKey, ['name' => $name]),
            tiles: $tiles,
        );
    }

    private function displayName(User $user): string
    {
        $name = trim((string) $user->name);
        if ($name === '') {
            return __('dashboard.intent.fallback_name');
        }

        $parts = preg_split('/\s+/u', $name) ?: [];

        return $parts[0] !== '' ? $parts[0] : $name;
    }

    private function greetingKey(CarbonInterface $now): string
    {
        $hour = (int) $now->timezone(config('app.timezone'))->format('G');

        if ($hour < 12) {
            return 'dashboard.intent.greeting.morning';
        }

        if ($hour < 18) {
            return 'dashboard.intent.greeting.afternoon';
        }

        return 'dashboard.intent.greeting.evening';
    }

    private function canAddWorker(User $user): bool
    {
        if (! $user->can('viewAny', InternalTeam::class)) {
            return false;
        }

        $team = InternalTeam::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($team === null) {
            // Nog geen team: stuur toch naar Personen → Teams (onboarding).
            return $user->isAdmin() || $user->isEmployee() || $user->is_superuser;
        }

        return $user->can('update', $team);
    }
}
