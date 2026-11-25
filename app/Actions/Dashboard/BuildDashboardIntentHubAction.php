<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Data\Dashboard\DashboardIntentHubData;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\CarbonInterface;

class BuildDashboardIntentHubAction
{
    /** @var list<string> */
    public const TILE_KEYS = [
        'day_task_favorites',
        'day_task',
        'location',
        'worker',
        'presence',
        'manual_clock_in',
        'today_tasks',
        'roster',
        'workers',
        'add_device',
    ];

    public function handle(Tenant $tenant, User $user, ?CarbonInterface $now = null): DashboardIntentHubData
    {
        $now ??= now();
        $name = $this->displayName($user);
        $greetingKey = $this->greetingKey($now);

        $tiles = [];

        if ($user->can('createInspectionRound', Issue::class)) {
            $tiles[] = [
                'key' => 'day_task_favorites',
                'icon' => 'star',
                'tone' => 'favorite',
                'title' => 'dashboard.intent.tiles.day_task_favorites.title',
                'body' => 'dashboard.intent.tiles.day_task_favorites.body',
                'href' => route('issues.index', [
                    'recurring' => 1,
                    'inspection_round' => 1,
                    'favorite_rounds' => 1,
                ]),
            ];

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

        if ($tenant->hasTimeModule()) {
            $tiles[] = [
                'key' => 'presence',
                'icon' => 'clock',
                'tone' => 'units',
                'title' => 'dashboard.intent.tiles.presence.title',
                'body' => 'dashboard.intent.tiles.presence.body',
                'href' => route('time.presence.index'),
            ];

            if ($user->can('manualClockIn', WorkShift::class)) {
                $tiles[] = [
                    'key' => 'manual_clock_in',
                    'icon' => 'clock',
                    'tone' => 'present_now',
                    'title' => 'dashboard.intent.tiles.manual_clock_in.title',
                    'body' => 'dashboard.intent.tiles.manual_clock_in.body',
                    'action' => 'openManualClockIn',
                ];
            }
        }

        if ($user->can('accessWorkMenuCalendar', $tenant)) {
            $tiles[] = [
                'key' => 'today_tasks',
                'icon' => 'calendar',
                'tone' => 'open_tasks',
                'title' => 'dashboard.intent.tiles.today_tasks.title',
                'body' => 'dashboard.intent.tiles.today_tasks.body',
                'href' => route('calendar.index', [
                    'view' => 'day',
                    'type' => 'tasks',
                    'date' => $now->timezone(config('app.timezone'))->toDateString(),
                ]),
            ];
        }

        if ($tenant->timeRosterEnabled()) {
            $tiles[] = [
                'key' => 'roster',
                'icon' => 'roster',
                'tone' => 'new_issues',
                'title' => 'dashboard.intent.tiles.roster.title',
                'body' => 'dashboard.intent.tiles.roster.body',
                'href' => route('time.schedule.index'),
            ];
        }

        // Na de Time/kalender-tegels: in een 4-kolomsraster onder "Uitvoerder toevoegen".
        if ($this->canAddWorker($user)) {
            $tiles[] = [
                'key' => 'workers',
                'icon' => 'team',
                'tone' => 'present_now',
                'title' => 'dashboard.intent.tiles.workers.title',
                'body' => 'dashboard.intent.tiles.workers.body',
                'href' => route('workers.index'),
            ];
        }

        if ($user->can('pairDeviceAny', Location::class)) {
            $tiles[] = [
                'key' => 'add_device',
                'icon' => 'key',
                'tone' => 'units',
                'title' => 'dashboard.add_device.title',
                'body' => 'dashboard.add_device.body',
                'action' => 'openAddDevice',
            ];
        }

        return new DashboardIntentHubData(
            greeting: __($greetingKey, ['name' => $name]),
            tiles: $this->applySavedOrder($tiles, $user->dashboard_intent_order),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $tiles
     * @param  list<mixed>|null  $saved
     * @return list<array<string, mixed>>
     */
    private function applySavedOrder(array $tiles, ?array $saved): array
    {
        if ($saved === null || $saved === []) {
            return $tiles;
        }

        $byKey = [];
        foreach ($tiles as $tile) {
            $byKey[$tile['key']] = $tile;
        }

        $ordered = [];
        foreach ($saved as $key) {
            if (! is_string($key) || ! isset($byKey[$key])) {
                continue;
            }
            $ordered[] = $byKey[$key];
            unset($byKey[$key]);
        }

        foreach ($tiles as $tile) {
            if (isset($byKey[$tile['key']])) {
                $ordered[] = $tile;
            }
        }

        return $ordered;
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
