<?php

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\ApplyPlanEntitlementsAction;
use App\Actions\Billing\UpdateBillingSeatsQtyAction;
use App\Actions\Customers\CreateCustomerWithLocationAction;
use App\Actions\Customers\SuggestCustomerNameMatchesAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\RequestPresenceComplianceAction;
use App\Actions\Time\StartWorkVisitAction;
use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Models\ClockPoint;
use App\Models\Customer;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkVisit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;

afterEach(fn () => Tenancy::forget());

function checkmateTenant(array $attrs = []): Tenant
{
    return Tenant::factory()->create(array_merge([
        'checkmate_mode' => true,
        'has_time_module' => true,
        'time_gps_visits' => true,
        'time_gps_visit_radius_meters' => 100,
        'billing_plan' => 'checkmate',
        'billing_active_until' => now()->addMonth(),
    ], $attrs));
}

function checkmateWorker(Tenant $tenant): Worker
{
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    return Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
}

it('provisioneert het checkmate-plan met GPS-bezoeken en een Clock Point', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => false,
        'time_gps_visits' => false,
    ]);
    $admin = User::factory()->admin()->for($tenant)->create();

    $fresh = app(ActivateSubscriptionPlanAction::class)
        ->handle($admin, $tenant, 'checkmate', 'platform')
        ->fresh();

    expect($fresh->checkmateMode())->toBeTrue()
        ->and($fresh->hasTimeModule())->toBeTrue()
        ->and($fresh->allowsGpsWorkVisits())->toBeTrue()
        ->and($fresh->gpsVisitRadiusMeters())->toBe(100)
        ->and($fresh->billing_seats_qty)->toBeGreaterThanOrEqual(1)
        // includes_facility=false maar een Clock Point is er wél (device-linking).
        ->and(ClockPoint::query()->where('tenant_id', $fresh->id)->count())->toBeGreaterThanOrEqual(1);
});

it('maakt klant + werkadres aan voor een worker en isoleert per tenant', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);

    $result = app(CreateCustomerWithLocationAction::class)->handle($worker, [
        'customer_name' => 'Bakkerij Peeters',
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '2000',
        'city' => 'Antwerpen',
        'latitude' => 51.22,
        'longitude' => 4.40,
    ]);

    expect($result['customer']->tenant_id)->toBe($tenant->id)
        ->and($result['location']->customer_id)->toBe($result['customer']->id)
        ->and($result['location']->tenant_id)->toBe($tenant->id)
        ->and($result['location']->hasWorkVisitPin())->toBeTrue()
        // Geen Facility site-unit voor Checkmate-werkadressen.
        ->and($result['location']->units()->count())->toBe(0);
});

it('weigert worker-flow met klant van een andere tenant', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);

    $other = Tenant::factory()->create();
    $foreignCustomer = Customer::factory()->create(['tenant_id' => $other->id]);

    expect(fn () => app(CreateCustomerWithLocationAction::class)->handle($worker, [
        'customer_id' => $foreignCustomer->id,
        'latitude' => 51.22,
        'longitude' => 4.40,
    ]))->toThrow(InvalidArgumentException::class, 'customer_not_found');
});

it('vereist GPS voor klant-aanmaak onderweg', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);

    expect(fn () => app(CreateCustomerWithLocationAction::class)->handle($worker, [
        'customer_name' => 'Klant Zonder GPS',
        'latitude' => null,
        'longitude' => null,
    ]))->toThrow(InvalidArgumentException::class, 'visit_gps_required');
});

it('toont een zachte dedup-nudge binnen de eigen tenant', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bakkerij Peeters']);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Fietsenmaker Jan']);

    $other = Tenant::factory()->create();
    Customer::factory()->create(['tenant_id' => $other->id, 'name' => 'Bakkerij Janssens']);

    $matches = app(SuggestCustomerNameMatchesAction::class)
        ->handle((int) $tenant->id, 'Bakkerij Peeter');

    expect($matches->pluck('name')->all())->toBe(['Bakkerij Peeters']);
});

it('start een werkbezoek op een klantlocatie binnen de straal', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'latitude' => 51.05,
        'longitude' => 3.73,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    $visit = app(StartWorkVisitAction::class)->handle($worker, $location, 51.0505, 3.7305);

    expect($visit->isOpen())->toBeTrue()
        ->and($visit->location_id)->toBe($location->id)
        ->and($visit->unit_id)->toBeNull();
});

it('blokkeert een werkbezoek buiten de straal (geen soft-fail)', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'latitude' => 51.05,
        'longitude' => 3.73,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    expect(fn () => app(StartWorkVisitAction::class)->handle($worker, $location, 51.10, 3.73))
        ->toThrow(InvalidArgumentException::class, 'visit_unit_out_of_range');
});

it('weigert een werkbezoek op een klantlocatie zonder pin', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'latitude' => null,
        'longitude' => null,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    expect(fn () => app(StartWorkVisitAction::class)->handle($worker, $location, 51.05, 3.73))
        ->toThrow(InvalidArgumentException::class, 'unit_visit_pin_missing');
});

it('queue-t geen CIAO-inzendingen vóór activering en backfillt nooit', function () {
    $tenant = checkmateTenant(['enterprise_number' => '0123456789']);
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'latitude' => 51.05,
        'longitude' => 3.73,
        'contractual_relationship_reference' => '1Y1003SQ5VSSZ',
    ]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    // Pending: aanvraag ingediend maar nog niet bevestigd → geen submissions.
    app(ClockInAction::class)->handle($worker, $clockPoint);
    expect(PresenceSubmission::query()->count())->toBe(0);

    // Activatie ná het event: compliance geldt op event-tijd, dus deze
    // pre-activatie-shift mag nooit alsnog een inzending krijgen.
    $tenant->update([
        'presence_compliance_enabled' => true,
        'presence_compliance_scope' => PresenceComplianceScope::CiaoCleaning->value,
        'presence_rsz_client_id' => 'test-client',
        'presence_rsz_private_key' => 'test-key',
    ]);

    expect(PresenceSubmission::query()->count())->toBe(0);

    // Nieuwe events na activering wél: visit-start queue-t een CIAO-inzending.
    app(StartWorkVisitAction::class)->handle($worker, $location, 51.05, 3.73);
    expect(PresenceSubmission::query()->count())->toBeGreaterThanOrEqual(1);
});

it('registreert een CIAO self-service aanvraag en houdt pending-status', function () {
    Mail::fake();
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);

    $fresh = app(RequestPresenceComplianceAction::class)->handle($tenant, [
        'enterprise_number' => 'BE0123.456.789',
    ], null);

    expect($fresh->presenceComplianceRequested())->toBeTrue()
        ->and($fresh->presence_compliance_enabled)->toBeFalse()
        ->and($fresh->enterprise_number)->toBe('0123456789')
        ->and($fresh->presence_compliance_scope)->toBe(PresenceComplianceScope::CiaoCleaning->value)
        ->and($fresh->presenceComplianceEnabled())->toBeFalse();

    Mail::assertSent(\App\Mail\PresenceComplianceRequestedMail::class);

    // Dubbele aanvraag is een no-op fout.
    expect(fn () => app(RequestPresenceComplianceAction::class)->handle($tenant->fresh(), [
        'enterprise_number' => '0123456789',
    ]))->toThrow(InvalidArgumentException::class, 'presence_request_pending');
});

it('vereist een ondernemingsnummer voor de CIAO-aanvraag', function () {
    Mail::fake();
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);

    expect(fn () => app(RequestPresenceComplianceAction::class)->handle($tenant, [
        'enterprise_number' => '',
    ]))->toThrow(InvalidArgumentException::class, 'enterprise_number_required');
});

it('bevestigt CIAO via de platform-toggle en wist de pending-status', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    // Pending-state (spec §6): scope gezet + enabled uit — geen statuskolom.
    $tenant->update([
        'enterprise_number' => '0123456789',
        'presence_compliance_scope' => PresenceComplianceScope::CiaoCleaning->value,
    ]);

    expect($tenant->fresh()->presenceComplianceRequested())->toBeTrue();

    app(\App\Actions\Platform\TogglePresenceComplianceAction::class)->handle($tenant);

    $fresh = $tenant->fresh();
    expect($fresh->presence_compliance_enabled)->toBeTrue()
        ->and($fresh->presenceComplianceRequested())->toBeFalse();
});

it('laat seat-qty wijzigen maar niet onder het actieve aantal', function () {
    $tenant = checkmateTenant([
        'billing_plan' => 'checkmate',
        'billing_active_until' => now()->addMonth(),
        'billing_seats_qty' => 3,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->for($tenant)->create();
    checkmateWorker($tenant);
    checkmateWorker($tenant); // admin + 2 workers = 3 actieve seats

    // Verminderen naar exact het actieve aantal is toegestaan.
    $fresh = app(UpdateBillingSeatsQtyAction::class)->handle($tenant->fresh(), 3, (int) $admin->id);
    expect($fresh->billing_seats_qty)->toBe(3);

    // Onder het actieve aantal → fout.
    expect(fn () => app(UpdateBillingSeatsQtyAction::class)->handle($tenant->fresh(), 2, (int) $admin->id))
        ->toThrow(InvalidArgumentException::class, 'seats_qty_below_active');
});

it('blokkeert niet-whitelist admin-routes voor checkmate-tenants', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $this->get('/issues')->assertNotFound();
    $this->get('/units')->assertNotFound();
    $this->get('/esg')->assertNotFound();
    $this->get('/locations')->assertNotFound();
    $this->get('/team')->assertNotFound();
    $this->get('/time/schedule')->assertNotFound();
    $this->get('/time/absence-requests')->assertNotFound();

    // Whitelist blijft bereikbaar.
    $this->get('/klanten')->assertOk();
    $this->get('/workers')->assertOk();
    $this->get('/settings')->assertOk();
    $this->get('/subscription')->assertOk();
    $this->get('/time/presence')->assertOk();
    $this->get('/time/shifts')->assertOk();
    $this->get('/time/ciao')->assertOk();
    $this->get('/time/clock-points')->assertOk();
});

it('laat facility-tenants ongemoeid door de checkmate-gate', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'checkmate_mode' => false,
        'trial_ends_at' => now()->addDays(14),
    ]);
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $this->get('/issues')->assertOk();
    $this->get('/klanten')->assertOk();
});
