<?php

use App\Actions\Customers\SummarizeCustomerWorkStatsAction;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Customers\Stats as CustomersStats;
use App\Models\Customer;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Models\WorkVisit;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * Tenant + intern team + worker + shift (allemaal dezelfde tenant_id).
 *
 * @return array{tenant: Tenant, team: InternalTeam, worker: Worker, shift: WorkShift}
 */
function statsContext(bool $timeModule = true): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => $timeModule]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
    ]);

    return ['tenant' => $tenant, 'team' => $team, 'worker' => $worker, 'shift' => $shift];
}

function statsVisit(array $ctx, Location $location, string $start, ?string $end): WorkVisit
{
    return WorkVisit::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'worker_id' => $ctx['worker']->id,
        'work_shift_id' => $ctx['shift']->id,
        'unit_id' => null,
        'location_id' => $location->id,
        'started_at' => CarbonImmutable::parse($start),
        'ended_at' => $end !== null ? CarbonImmutable::parse($end) : null,
    ]);
}

test('bezoek over de maandgrens wordt geclampt: één bezoek per maand, minuten gesplitst', function () {
    $ctx = statsContext();
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $location = Location::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $customer->id,
    ]);

    statsVisit($ctx, $location, '2026-09-30 23:50', '2026-10-01 00:20');

    $action = app(SummarizeCustomerWorkStatsAction::class);

    $september = $action->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-09-01 00:00'),
        CarbonImmutable::parse('2026-10-01 00:00'),
    )->get($customer->id);

    expect($september->visits)->toBe(1)
        ->and($september->minutes)->toBe(10);

    $october = $action->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    )->get($customer->id);

    expect($october->visits)->toBe(1)
        ->and($october->minutes)->toBe(20);
});

test('open bezoek telt mee tot now()', function () {
    CarbonImmutable::setTestNow('2026-10-15 12:00');

    $ctx = statsContext();
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $location = Location::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $customer->id,
    ]);

    statsVisit($ctx, $location, '2026-10-15 11:00', null);

    $stats = app(SummarizeCustomerWorkStatsAction::class)->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    )->get($customer->id);

    expect($stats->visits)->toBe(1)
        ->and($stats->minutes)->toBe(60);

    CarbonImmutable::setTestNow();
});

test('twee werkadressen van dezelfde klant aggregeren correct', function () {
    $ctx = statsContext();
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $locationA = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customer->id]);
    $locationB = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customer->id]);

    statsVisit($ctx, $locationA, '2026-10-02 08:00', '2026-10-02 09:00');
    statsVisit($ctx, $locationB, '2026-10-03 08:00', '2026-10-03 09:30');

    $stats = app(SummarizeCustomerWorkStatsAction::class)->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    )->get($customer->id);

    expect($stats->visits)->toBe(2)
        ->and($stats->minutes)->toBe(150)
        ->and($stats->visitedLocations())->toBe(2)
        ->and($stats->locations[$locationA->id]->minutes)->toBe(60)
        ->and($stats->locations[$locationB->id]->minutes)->toBe(90);
});

test('aggregatie loopt via customer_id, niet enkel via locatie', function () {
    $ctx = statsContext();
    $customerA = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $locationA = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customerA->id]);
    $locationB = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customerB->id]);

    statsVisit($ctx, $locationA, '2026-10-02 08:00', '2026-10-02 09:00');
    statsVisit($ctx, $locationB, '2026-10-02 10:00', '2026-10-02 11:30');

    $stats = app(SummarizeCustomerWorkStatsAction::class)->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    );

    expect($stats->get($customerA->id)->minutes)->toBe(60)
        ->and($stats->get($customerB->id)->minutes)->toBe(90);
});

test('locaties zonder klant vallen buiten klantstatistieken', function () {
    $ctx = statsContext();
    $location = Location::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => null,
    ]);

    statsVisit($ctx, $location, '2026-10-02 08:00', '2026-10-02 09:00');

    $stats = app(SummarizeCustomerWorkStatsAction::class)->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    );

    expect($stats)->toBeEmpty();
});

test('gedeactiveerd werkadres telt historisch mee', function () {
    $ctx = statsContext();
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $location = Location::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $customer->id,
        'is_active' => false,
    ]);

    statsVisit($ctx, $location, '2026-10-02 08:00', '2026-10-02 09:00');

    $stats = app(SummarizeCustomerWorkStatsAction::class)->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    )->get($customer->id);

    expect($stats->minutes)->toBe(60);
});

test('bezoeken van een andere tenant worden niet meegeteld', function () {
    $ctx = statsContext();
    $other = statsContext();

    $customerA = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $other['tenant']->id]);
    $locationA = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customerA->id]);
    $locationB = Location::factory()->create(['tenant_id' => $other['tenant']->id, 'customer_id' => $customerB->id]);

    statsVisit($ctx, $locationA, '2026-10-02 08:00', '2026-10-02 09:00');
    statsVisit($other, $locationB, '2026-10-02 08:00', '2026-10-02 10:00');

    Tenancy::actAs($ctx['tenant']->id);
    $stats = app(SummarizeCustomerWorkStatsAction::class)->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    );

    expect($stats->keys()->all())->toBe([$customerA->id]);
});

test('bezoeken buiten de periode tellen niet mee', function () {
    $ctx = statsContext();
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $location = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customer->id]);

    statsVisit($ctx, $location, '2026-09-15 08:00', '2026-09-15 09:00');

    $stats = app(SummarizeCustomerWorkStatsAction::class)->handle(
        $ctx['tenant']->id,
        CarbonImmutable::parse('2026-10-01 00:00'),
        CarbonImmutable::parse('2026-11-01 00:00'),
    );

    expect($stats->get($customer->id))->toBeNull();
});

test('statistiekpagina werkt met Time-module, niet zonder', function () {
    $ctx = statsContext();
    $user = User::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $location = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customer->id]);
    statsVisit($ctx, $location, '2026-10-02 08:00', '2026-10-02 09:00');

    Livewire::actingAs($user)
        ->test(CustomersStats::class, ['customer' => $customer, 'month' => '2026-10'])
        ->assertOk();

    $ctxNoTime = statsContext(timeModule: false);
    $userNoTime = User::factory()->create(['tenant_id' => $ctxNoTime['tenant']->id]);
    $customerNoTime = Customer::factory()->create(['tenant_id' => $ctxNoTime['tenant']->id]);

    Livewire::actingAs($userNoTime)
        ->test(CustomersStats::class, ['customer' => $customerNoTime])
        ->assertNotFound();
});

test('klantenlijst toont stats enkel met Time-module', function () {
    $ctx = statsContext();
    $user = User::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $location = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customer->id]);
    statsVisit($ctx, $location, now()->format('Y-m-d').' 08:00', now()->format('Y-m-d').' 09:00');

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->assertSee(route('customers.stats', $customer));

    $ctxNoTime = statsContext(timeModule: false);
    $userNoTime = User::factory()->create(['tenant_id' => $ctxNoTime['tenant']->id]);
    Customer::factory()->create(['tenant_id' => $ctxNoTime['tenant']->id]);

    Tenancy::actAs($ctxNoTime['tenant']->id);
    Livewire::actingAs($userNoTime)->test(CustomersIndex::class)
        ->assertDontSee('statistieken');
});

test('statistiekpagina toont nulwaarden zonder bezoeken', function () {
    $ctx = statsContext();
    $user = User::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);

    Livewire::actingAs($user)
        ->test(CustomersStats::class, ['customer' => $customer])
        ->assertOk()
        ->assertSee(__('customers.stats.empty'));
});

test('export geeft een CSV terug', function () {
    $ctx = statsContext();
    $user = User::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $customer = Customer::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $location = Location::factory()->create(['tenant_id' => $ctx['tenant']->id, 'customer_id' => $customer->id]);
    statsVisit($ctx, $location, '2026-10-02 08:00', '2026-10-02 09:00');

    $this->actingAs($user)
        ->get(route('customers.stats.export', ['customer' => $customer, 'month' => '2026-10']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('statistiekpagina weigert een klant van een andere tenant', function () {
    $ctx = statsContext();
    $user = User::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => statsContext()['tenant']->id]);

    Livewire::actingAs($user)
        ->test(CustomersStats::class, ['customer' => $otherCustomer])
        ->assertForbidden();
});
