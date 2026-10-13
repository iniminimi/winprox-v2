<?php

declare(strict_types=1);

use App\Actions\Team\BackfillWorkerWeekPatternsAction;
use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\SeedWorkerWeekPatternAction;
use App\Actions\Time\ResolveWorkerMonthNormAction;
use App\Enums\MonthNormStatus;
use App\Livewire\Pages\Team;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function weekPatternTenant(bool $timeModule = true): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => $timeModule]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin, $team];
}

/** @return array<int, int> */
function weekPatternOf(Worker $worker): array
{
    return $worker->weekdayMinutes()->pluck('minutes', 'weekday')->map(fn ($m) => (int) $m)->all();
}

it('geeft een nieuwe uitvoerder automatisch de standaard ma–vr-regeling', function () {
    [$tenant, $admin, $team] = weekPatternTenant();

    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Sven',
        'last_name' => 'Peeters',
    ], (int) $admin->id);

    expect((int) $worker->fresh()->weekly_contract_minutes)->toBe(Worker::DEFAULT_WEEKLY_CONTRACT_MINUTES)
        ->and(weekPatternOf($worker))->toBe([1 => 456, 2 => 456, 3 => 456, 4 => 456, 5 => 456, 6 => 0, 7 => 0]);

    // Tweede aanroep is idempotent: bestaand patroon blijft ongemoeid.
    expect(app(SeedWorkerWeekPatternAction::class)->handle($worker->fresh()))->toBeFalse();
});

it('verdeelt een afwijkende contractnorm over ma–vr bij het aanmaken', function () {
    [$tenant, $admin, $team] = weekPatternTenant();

    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Els',
        'last_name' => 'De Smet',
        'weekly_contract_minutes' => 1900,
    ], (int) $admin->id);

    expect(weekPatternOf($worker))->toBe([1 => 380, 2 => 380, 3 => 380, 4 => 380, 5 => 380, 6 => 0, 7 => 0]);
});

it('seedet niets voor een tenant zonder time-module', function () {
    [, $admin, $team] = weekPatternTenant(false);

    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Jan',
        'last_name' => 'Jansen',
    ], (int) $admin->id);

    expect(weekPatternOf($worker))->toBe([]);
});

it('behandelt het standaardpatroon als expliciete regeling in de maandnorm', function () {
    [$tenant, $admin, $team] = weekPatternTenant();
    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Noor',
        'last_name' => 'Verbeke',
    ], (int) $admin->id);

    $norm = app(ResolveWorkerMonthNormAction::class)->handle(
        (int) $tenant->id,
        [(int) $worker->id],
        '2026-02-01',
    )[(int) $worker->id];

    $expected = 0;
    $day = Carbon::parse('2026-02-01')->startOfMonth();
    while ($day->isSameMonth(Carbon::parse('2026-02-01'))) {
        $expected += Worker::defaultWeekPattern(2280)[(int) $day->dayOfWeekIso];
        $day->addDay();
    }

    expect($norm->status)->toBe(MonthNormStatus::Available)
        ->and($norm->minutes)->toBe($expected);
});

it('vult de create-modal met de standaardregeling en respecteert een eigen patroon', function () {
    [$tenant, $admin, $team] = weekPatternTenant();

    // Vierdagenweek: vrijdag op 0, contract omlaag naar 1824 (4 × 456).
    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openAddWorker', $team->id)
        ->assertSet('workerWeekdayMinutes.1', '456')
        ->assertSet('workerWeekdayMinutes.6', '0')
        ->set('workerContractHours', 30)
        ->set('workerContractMinutes', 24)
        ->set('workerWeekdayMinutes.5', '0')
        ->set('workerWeekdayMinutes.1', '456')
        ->set('workerFirstName', 'Koen')
        ->set('workerLastName', 'Claes')
        ->call('saveWorker')
        ->assertHasNoErrors();

    $worker = Worker::query()->where('first_name', 'Koen')->firstOrFail();
    expect((int) $worker->weekly_contract_minutes)->toBe(1824)
        ->and(weekPatternOf($worker))->toBe([1 => 456, 2 => 456, 3 => 456, 4 => 456, 5 => 0, 6 => 0, 7 => 0]);
});

it('backfillt patroonloze uitvoerders en laat bestaande patronen ongemoeid', function () {
    [$tenant, , $team] = weekPatternTenant();

    $plain = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'weekly_contract_minutes' => 2280,
    ]);
    $nullContract = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'weekly_contract_minutes' => null,
    ]);
    $parttime = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'weekly_contract_minutes' => 1900,
    ]);
    $existing = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    foreach (range(1, 7) as $day) {
        WorkerWeekdayMinute::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $existing->id,
            'weekday' => $day,
            'minutes' => $day <= 5 ? 400 : 0,
        ]);
    }
    $existing->forceFill(['weekly_contract_minutes' => 2000])->save();
    $partial = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    WorkerWeekdayMinute::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $partial->id,
        'weekday' => 1,
        'minutes' => 456,
    ]);

    $backfill = app(BackfillWorkerWeekPatternsAction::class);

    $preview = $backfill->handle($tenant, dryRun: true);
    expect($preview['workers'])->toBe(5)
        ->and($preview['already_patterned'])->toBe(1)
        ->and($preview['seeded'])->toBe(2) // $plain + $nullContract (null → 2280)
        ->and($preview['seeded_deviating'])->toBe(1) // $parttime
        ->and($preview['contract_filled'])->toBe(1) // $nullContract
        ->and($preview['incomplete_patterns'])->toBe(1) // $partial
        ->and($preview['deviating_workers'][0]['id'])->toBe((int) $parttime->id)
        ->and($preview['incomplete_workers'][0]['id'])->toBe((int) $partial->id);

    // Dry-run schrijft niets.
    expect(weekPatternOf($plain->fresh()))->toBe([]);

    $result = $backfill->handle($tenant);
    expect($result['seeded'])->toBe(2)
        ->and(weekPatternOf($plain->fresh()))->toBe([1 => 456, 2 => 456, 3 => 456, 4 => 456, 5 => 456, 6 => 0, 7 => 0])
        ->and(weekPatternOf($nullContract->fresh()))->toBe([1 => 456, 2 => 456, 3 => 456, 4 => 456, 5 => 456, 6 => 0, 7 => 0])
        ->and((int) $nullContract->fresh()->weekly_contract_minutes)->toBe(2280)
        ->and(weekPatternOf($parttime->fresh()))->toBe([1 => 380, 2 => 380, 3 => 380, 4 => 380, 5 => 380, 6 => 0, 7 => 0])
        ->and(weekPatternOf($existing->fresh()))->toBe([1 => 400, 2 => 400, 3 => 400, 4 => 400, 5 => 400, 6 => 0, 7 => 0])
        ->and(array_keys(weekPatternOf($partial->fresh())))->toBe([1]);

    // Idempotent: tweede run verandert niets.
    $again = $backfill->handle($tenant);
    expect($again['seeded'])->toBe(0)
        ->and($again['seeded_deviating'])->toBe(0)
        ->and($again['already_patterned'])->toBe(4);
});

it('voert het artisan-command uit met dry-run tellingen per tenant', function () {
    [$tenant, , $team] = weekPatternTenant();
    Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    $this->artisan('workers:seed-week-pattern', ['--tenant' => $tenant->id, '--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('aangevuld (38u):         1')
        ->assertSuccessful();

    $this->artisan('workers:seed-week-pattern', ['--tenant' => $tenant->id])
        ->expectsOutputToContain('aangevuld (38u):         1')
        ->assertSuccessful();

    $this->artisan('workers:seed-week-pattern', ['--tenant' => $tenant->id])
        ->expectsOutputToContain('aangevuld (38u):         0')
        ->expectsOutputToContain('al een volledig patroon: 1')
        ->assertSuccessful();
});
