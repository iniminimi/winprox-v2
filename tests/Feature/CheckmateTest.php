<?php

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\ApplyPlanEntitlementsAction;
use App\Actions\Billing\UpdateBillingSeatsQtyAction;
use App\Actions\Customers\CreateCustomerWithLocationAction;
use App\Actions\Customers\SuggestCustomerNameMatchesAction;
use App\Actions\Portal\ResolveWorkerIdentityForTenantAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\RequestPresenceComplianceAction;
use App\Actions\Time\StartWorkVisitAction;
use App\Actions\Time\SuggestNearbyClockUnitsAction;
use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\WorkerIdentityStatus;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Dashboard;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Public\TimePortal;
use App\Livewire\Pages\Subscription;
use App\Livewire\Pages\Team as TeamPage;
use App\Livewire\Platform\Tenants as PlatformTenants;
use App\Models\ClockPoint;
use App\Models\Customer;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Models\WorkVisit;
use App\Support\Billing\BillingCatalogViewData;
use App\Support\Locations\GoogleMapsAddressLine;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;
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

function checkmateCustomerLocation(Tenant $tenant, array $attrs = []): Location
{
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    return Location::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ], $attrs));
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
        // CIAO staat meteen aan (essentie van Checkmate); tenant vult het
        // ondernemingsnummer later in via Instellingen.
        ->and($fresh->presenceComplianceEnabled())->toBeTrue()
        ->and($fresh->presenceComplianceScope())->toBe(\App\Enums\PresenceComplianceScope::CiaoCleaning)
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

it('verwijdert een klant zonder werkadressen, maar niet met werkadressen', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    $empty = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $linked = Customer::factory()->create(['tenant_id' => $tenant->id]);
    Location::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $linked->id]);

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->assertSee('deleteCustomer('.$empty->id.')')
        ->assertDontSee('deleteCustomer('.$linked->id.')')
        ->call('deleteCustomer', $empty->id);

    expect(Customer::find($empty->id))->toBeNull()
        ->and(Customer::find($linked->id))->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('deleteCustomer', $linked->id);

    expect(Customer::find($linked->id))->not->toBeNull();
});

it('start een werkbezoek op een klantlocatie binnen de straal', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $location = checkmateCustomerLocation($tenant, [
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
    $location = checkmateCustomerLocation($tenant, [
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
    $location = checkmateCustomerLocation($tenant, [
        'latitude' => null,
        'longitude' => null,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    expect(fn () => app(StartWorkVisitAction::class)->handle($worker, $location, 51.05, 3.73))
        ->toThrow(InvalidArgumentException::class, 'unit_visit_pin_missing');
});

it('suggereert enkel klant-werkadressen voor checkmate — geen legacy locaties of units', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);

    $customerLocation = checkmateCustomerLocation($tenant, [
        'name' => 'Klant werkadres',
        'latitude' => 51.05,
        'longitude' => 3.73,
    ]);
    Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Legacy locatie',
        'latitude' => 51.0501,
        'longitude' => 3.7301,
    ]);
    $legacyLocation = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Legacy met unit',
        'latitude' => 51.0502,
        'longitude' => 3.7302,
    ]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $legacyLocation->id,
        'latitude' => 51.0502,
        'longitude' => 3.7302,
    ]);

    $suggestions = app(SuggestNearbyClockUnitsAction::class)->handle($worker, 51.05, 3.73);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->locationId)->toBe($customerLocation->id);
});

it('weigert een werkbezoek op een legacy-locatie zonder klant', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $legacy = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'latitude' => 51.05,
        'longitude' => 3.73,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    expect(fn () => app(StartWorkVisitAction::class)->handle($worker, $legacy, 51.05, 3.73))
        ->toThrow(InvalidArgumentException::class, 'visit_requires_customer_location');
});

it('toont in checkmate geen geplande-bezoekenlijst maar wel de klant-zoekknop', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $team = $worker->team;

    WorkerVerification::markVerified($team, $worker);
    WorkerDeviceSession::bindRememberedWorker($team, $worker);
    app(ClockInAction::class)->handle($worker, $clockPoint);

    $html = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSee(__('time.portal.today.title_checkmate'))
        ->assertSee(__('time.portal.clock.find_nearby_customer'))
        ->assertDontSee('x-show="!hasStartWorkInRange()" x-cloak', false)
        ->assertSee('x-teleport="body"', false)
        ->assertDontSee(__('time.portal.today.empty'))
        ->assertDontSee(__('time.portal.today.title'))
        ->html();

    // Dubbele quotes in x-data breken het attribuut; Safari toont de JS dan als tekst.
    expect($html)->toMatch('/x-data="[^"]*refreshHere\(\)[^"]*geolocation[^"]*"/s');
});

it('kleurt het statusblok groen met ingeklokt-icoon na inklokken', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $team = $worker->team;

    WorkerVerification::markVerified($team, $worker);
    WorkerDeviceSession::bindRememberedWorker($team, $worker);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSee('data-clock-state="out"', false)
        ->assertSee('wp-portal-now__divider', false)
        ->assertDontSee('wp-portal-now--clocked', false)
        ->assertDontSee(__('time.portal.now.kicker_in'));

    app(ClockInAction::class)->handle($worker, $clockPoint);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSee('wp-portal-now--clocked', false)
        ->assertSee('data-clock-state="in"', false)
        ->assertSee(__('time.portal.now.kicker_in'))
        ->assertDontSee('data-clock-state="out"', false);
});

it('laat de ingeklokt-flash na twee seconden automatisch verdwijnen', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $team = $worker->team;

    WorkerVerification::markVerified($team, $worker);
    WorkerDeviceSession::bindRememberedWorker($team, $worker);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('clockIn')
        ->assertSet('flashMessageKey', 'time.portal.clock.clocked_in_at_tenant')
        ->assertSee('setTimeout(() => hide = true, 2000)', false);
});

it('toont geen teamleader-tegel aan een gewone uitvoerder', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $team = $worker->team;

    WorkerVerification::markVerified($team, $worker);
    WorkerDeviceSession::bindRememberedWorker($team, $worker);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertDontSee('openTeamleader', false)
        ->call('openTeamleader')
        ->assertDontSee(__('portal.teamleader.page_title'), false);
});

it('verbergt teamleader-functies achter een tegel op het checkmate-portaal', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $leader = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    WorkerVerification::markVerified($team, $leader);
    WorkerDeviceSession::bindRememberedWorker($team, $leader);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSee('openTeamleader', false)
        ->assertDontSee('toggleReleasePanel', false)
        ->assertDontSee(__('portal.teamleader.page_subtitle'), false)
        ->call('openTeamleader')
        ->assertSee(__('portal.teamleader.page_subtitle'), false)
        ->call('closeTeamleader')
        ->assertDontSee(__('portal.teamleader.page_subtitle'), false)
        ->assertSee('openTeamleader', false);
});

it('laat een teamleader enkel een zelf net aangemaakte uitvoerder verwijderen', function () {
    $tenant = checkmateTenant(['billing_seats_qty' => 10]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $leader = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
    ]);
    $otherLeader = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    WorkerVerification::markVerified($team, $leader);
    WorkerDeviceSession::bindRememberedWorker($team, $leader);

    // Door admin aangemaakt (created_by_worker_id = null) → niet verwijderbaar.
    $adminWorker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    // Door een andere teamleader aangemaakt → niet verwijderbaar.
    $otherLeaderWorker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'created_by_worker_id' => $otherLeader->id,
    ]);

    // Zelf aangemaakt, maar ouder dan 24 uur → niet verwijderbaar.
    $oldWorker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'created_by_worker_id' => $leader->id,
        'created_at' => now()->subDays(2),
    ]);

    $component = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('removeWorker', $adminWorker->id)
        ->call('removeWorker', $otherLeaderWorker->id)
        ->call('removeWorker', $oldWorker->id)
        // De lijst toont enkel uitvoerders die de teamleader ook mag verwijderen.
        ->assertViewHas('teamWorkers', fn ($list) => $list->isEmpty());

    expect(Worker::find($adminWorker->id))->not->toBeNull()
        ->and(Worker::find($otherLeaderWorker->id))->not->toBeNull()
        ->and(Worker::find($oldWorker->id))->not->toBeNull();

    // Zelf net aangemaakt via het portaal → wél verwijderbaar.
    $component
        ->set('newWorkerFirstName', 'Nieuwe')
        ->set('newWorkerLastName', 'Collega')
        ->call('addWorker');

    $newWorker = Worker::where('internal_team_id', $team->id)
        ->where('first_name', 'Nieuwe')
        ->firstOrFail();
    expect((int) $newWorker->created_by_worker_id)->toBe($leader->id);

    $component->assertViewHas('teamWorkers', fn ($list) => $list->pluck('id')->all() === [$newWorker->id])
        ->call('removeWorker', $newWorker->id);

    expect(Worker::find($newWorker->id))->toBeNull();
});

it('vindt checkmate-uitvoerders ondanks legacy-locatiebeperking op het clock point', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);

    // Worker heeft een facility-erfenis: toegewezen locatie ≠ clock-point-locatie.
    $legacyLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    $worker->locations()->sync([$legacyLocation->id]);
    $clockPointLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $clockPointLocation->id,
    ]);

    $result = app(ResolveWorkerIdentityForTenantAction::class)->handle(
        $tenant->id,
        (string) $worker->first_name,
        (string) $worker->last_name,
        (int) $clockPointLocation->id,
    );

    expect($result['status'])->not->toBe(WorkerIdentityStatus::NotFound)
        ->and($result['worker']->id)->toBe($worker->id);
});

it('respecteert de locatiebeperking nog wel voor facility-tenants', function () {
    $tenant = Tenant::factory()->create(['checkmate_mode' => false]);
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $workerLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    $worker->locations()->sync([$workerLocation->id]);
    $otherLocation = Location::factory()->create(['tenant_id' => $tenant->id]);

    $result = app(ResolveWorkerIdentityForTenantAction::class)->handle(
        $tenant->id,
        (string) $worker->first_name,
        (string) $worker->last_name,
        (int) $otherLocation->id,
    );

    expect($result['status'])->toBe(WorkerIdentityStatus::NotFound);
});

it('weigert een werkbezoek op een werkadres van een inactieve klant', function () {
    $tenant = checkmateTenant();
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $location = checkmateCustomerLocation($tenant, [
        'latitude' => 51.05,
        'longitude' => 3.73,
    ]);
    $location->customer->update(['is_active' => false]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    expect(fn () => app(StartWorkVisitAction::class)->handle($worker, $location, 51.05, 3.73))
        ->toThrow(InvalidArgumentException::class, 'visit_requires_customer_location');
});

it('queue-t geen CIAO-inzendingen vóór activering en backfillt nooit', function () {
    $tenant = checkmateTenant(['enterprise_number' => '0123456789']);
    Tenancy::actAs($tenant->id);
    $worker = checkmateWorker($tenant);
    $location = checkmateCustomerLocation($tenant, [
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

it('zet CIAO aan bij checkmate-entitlements en toont de BCE-nudge tot het nummer ingevuld is', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    Tenancy::actAs($tenant->id);

    // Zelfde pad als onboarding/plan-activatie.
    app(ApplyPlanEntitlementsAction::class)->handle($tenant);
    $tenant->refresh();

    expect($tenant->presenceComplianceEnabled())->toBeTrue()
        ->and($tenant->presenceComplianceScope())->toBe(PresenceComplianceScope::CiaoCleaning)
        ->and($tenant->presenceComplianceRequested())->toBeFalse();

    // Instellingen tonen de actieve CIAO-sectie, niet het aanvraagformulier.
    $this->actingAs($admin)
        ->get('/settings')
        ->assertOk()
        ->assertSee(__('settings.presence.save'), false)
        ->assertDontSee(__('settings.presence.request_submit'), false);

    // CIAO aan maar nog geen BCE → nudge op het dashboard.
    $this->get('/dashboard')
        ->assertOk()
        ->assertSee(__('dashboard.checkmate.ciao.missing_employer_title'), false);

    // BCE ingevuld → nudge weg (unsetRelation: de guard-cache houdt de oude
    // tenant-relatie vast, zelfde patroon als Dashboard-mutaties).
    $tenant->update(['enterprise_number' => '0123456789']);
    $admin->unsetRelation('tenant');
    $this->get('/dashboard')
        ->assertOk()
        ->assertDontSee(__('dashboard.checkmate.ciao.missing_employer_title'), false);
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
    $this->get('/time/schedule')->assertNotFound();
    $this->get('/time/absence-requests')->assertNotFound();

    // Whitelist blijft bereikbaar.
    $this->get('/klanten')->assertOk();
    $this->get('/workers')->assertOk();
    // Uitvoerder-rij linkt naar het beheer op /team (whitelisted).
    $this->get('/team?section=teams&worker='.checkmateWorker($tenant)->id)->assertOk();
    $this->get('/settings')->assertOk();
    $this->get('/subscription')->assertOk();
    $this->get('/time/presence')->assertOk();
    $this->get('/time/shifts')->assertOk();
    $this->get('/time/ciao')->assertOk();
    $this->get('/time/clock-points')->assertOk();
});

it('linkt op toegestane time-pagina’s nooit naar gated routes voor checkmate', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $gated = ['/time/schedule', '/time/absence-requests', '/time/shift-types', '/time/alarms'];

    foreach (['/time/presence', '/time/shifts', '/time/ciao', '/time/clock-points'] as $page) {
        $response = $this->get($page)->assertOk();

        foreach ($gated as $path) {
            $response->assertDontSee('href="http://localhost'.$path, false);
        }
    }
});

it('verbergt werkmenu en configuratie-overzicht op instellingen voor checkmate', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get('/settings')
        ->assertOk()
        ->assertSee(__('settings.org.title'), false)
        ->assertDontSee('settings-config-overview', false)
        ->assertDontSee('loadConfigOverview', false)
        ->assertDontSee('saveWorkMenuSettings', false)
        ->assertDontSee(__('settings.config_overview.title'), false)
        ->assertDontSee(__('settings.work_menu.title'), false)
        ->assertDontSee(__('settings.notifications.title'), false)
        ->assertDontSee(__('settings.notifications.new_qr_issue_label'), false)
        ->assertDontSee(__('settings.time_clock.evacuation_list'), false);

    $facilityTenant = Tenant::factory()->create([
        'checkmate_mode' => false,
        'has_time_module' => true,
        'trial_ends_at' => now()->addDays(14),
    ]);
    $facilityAdmin = User::factory()->admin()->for($facilityTenant)->create();

    $this->actingAs($facilityAdmin)
        ->get('/settings')
        ->assertOk()
        ->assertSee('settings-config-overview', false)
        ->assertSee('saveWorkMenuSettings', false)
        ->assertSee(__('settings.config_overview.title'), false)
        ->assertSee(__('settings.work_menu.title'), false)
        ->assertSee(__('settings.notifications.title'), false)
        ->assertSee(__('settings.notifications.new_qr_issue_label'), false)
        ->assertSee(__('settings.time_clock.evacuation_list'), false);
});

it('verbergt Manueel inklokken op Time-pagina’s voor checkmate', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    foreach (['/time/shifts', '/time/presence'] as $page) {
        $this->actingAs($admin)
            ->get($page)
            ->assertOk()
            ->assertDontSee('openManualClockIn', false)
            ->assertDontSee(__('time.manual_clock_in.button'), false);
    }

    $facilityTenant = Tenant::factory()->create([
        'checkmate_mode' => false,
        'has_time_module' => true,
        'trial_ends_at' => now()->addDays(14),
    ]);
    $facilityAdmin = User::factory()->admin()->for($facilityTenant)->create();

    foreach (['/time/shifts', '/time/presence'] as $page) {
        $this->actingAs($facilityAdmin)
            ->get($page)
            ->assertOk()
            ->assertSee('openManualClockIn', false);
    }
});

it('verbergt locatiebeperking in uitvoerder- en team-modals voor checkmate', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $worker = checkmateWorker($tenant);

    Livewire::actingAs($admin)
        ->test(TeamPage::class)
        ->call('openEditWorker', $worker->id)
        ->assertDontSee('toggleWorkerLocationsPanel', false)
        ->assertDontSee(__('team.workers.modal.locations_summary_all'), false);

    Livewire::actingAs($admin)
        ->test(TeamPage::class)
        ->call('openCreateTeam')
        ->assertDontSee('teamClocksAllLocations', false)
        ->assertDontSee(__('team.teams.modal.clocks_all_locations'), false);

    $facilityTenant = Tenant::factory()->create([
        'checkmate_mode' => false,
        'has_time_module' => true,
        'trial_ends_at' => now()->addDays(14),
    ]);
    $facilityAdmin = User::factory()->admin()->for($facilityTenant)->create();
    $facilityWorker = checkmateWorker($facilityTenant);

    Livewire::actingAs($facilityAdmin)
        ->test(TeamPage::class)
        ->call('openEditWorker', $facilityWorker->id)
        ->assertSee('toggleWorkerLocationsPanel', false);

    Livewire::actingAs($facilityAdmin)
        ->test(TeamPage::class)
        ->call('openCreateTeam')
        ->assertSee('teamClocksAllLocations', false);
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

it('laat een tenant zelf het checkmate-plan kiezen op Abonnement', function () {
    config([
        'billing.allow_tenant_self_activation' => true,
        'stripe.enabled' => false,
    ]);

    $tenant = Tenant::factory()->create([
        'checkmate_mode' => false,
        'trial_ends_at' => now()->addDays(5),
    ]);
    $admin = User::factory()->admin()->for($tenant)->create();

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->call('activatePlan', 'checkmate')
        ->assertHasNoErrors();

    $fresh = $tenant->fresh();

    expect($fresh->billing_plan)->toBe('checkmate')
        ->and($fresh->checkmateMode())->toBeTrue()
        ->and($fresh->hasTimeModule())->toBeTrue()
        ->and($fresh->allowsGpsWorkVisits())->toBeTrue()
        ->and($fresh->gpsVisitRadiusMeters())->toBe(100);
});

it('laat een superuser checkmate_trial toewijzen via Platform', function () {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create([
        'checkmate_mode' => false,
        'trial_ends_at' => now()->addDays(5),
    ]);

    Livewire::actingAs($super)
        ->test(PlatformTenants::class)
        ->set('planInputs.'.$tenant->id, 'checkmate_trial')
        ->call('assignPlan', $tenant->id)
        ->assertHasNoErrors();

    $fresh = $tenant->fresh();

    expect($fresh->billing_plan)->toBe('checkmate_trial')
        ->and($fresh->checkmateMode())->toBeTrue()
        ->and($fresh->maxSeatsLimit())->toBe(3);
});

it('verbergt checkmate_trial uit de publieke catalogus maar niet uit Platform', function () {
    expect(BillingCatalogViewData::publicPlanKeys())
        ->toContain('checkmate')
        ->not->toContain('checkmate_trial');

    expect(BillingCatalogViewData::platformPlanKeys())
        ->toContain('checkmate')
        ->toContain('checkmate_trial');
});

it('toont het checkmate-dashboard met veldwerk-tegels en zonder facility-links', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $worker = checkmateWorker($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);
    WorkVisit::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_shift_id' => WorkShift::factory()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $worker->id,
        ])->id,
        'unit_id' => null,
        'location_id' => $location->id,
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('dashboard.checkmate.kpi.visits_today'))
        ->assertSee(__('dashboard.checkmate.recent.title'))
        ->assertSee($customer->name)
        ->assertDontSee(__('dashboard.add_issue'))
        ->assertDontSee(__('dashboard.recent.title'))
        ->assertDontSee(route('issues.index'));
});

it('toont checkmate-onboarding voor uitvoerders in plaats van facility-starter packs', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('dashboard.checkmate.onboarding.workers_title'))
        ->assertDontSee(__('dashboard.starter_pack.offer_title'));
});

it('houdt het facility-dashboard ongewijzigd voor niet-checkmate tenants', function () {
    $tenant = Tenant::factory()->create([
        'checkmate_mode' => false,
        'trial_ends_at' => now()->addDays(5),
        'has_time_module' => true,
    ]);
    $admin = User::factory()->admin()->for($tenant)->create();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertViewHas('checkmate', null);
});

it('maakt een klant aan via het formulier op /klanten', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('openCreate')
        ->set('customerFormName', 'Kantoor Peeters')
        ->set('customerFormEmail', 'info@peeters.be')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    $customer = Customer::where('tenant_id', $tenant->id)->sole();

    expect($customer->name)->toBe('Kantoor Peeters')
        ->and($customer->email)->toBe('info@peeters.be')
        ->and($customer->is_active)->toBeTrue();
});

it('werkt een bestaande klant bij via het formulier op /klanten', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Oude naam',
    ]);

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('openEdit', $customer->id)
        ->set('customerFormName', 'Nieuwe naam')
        ->set('customerFormPhone', '0470 12 34 56')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    $fresh = $customer->fresh();

    expect($fresh->name)->toBe('Nieuwe naam')
        ->and($fresh->phone)->toBe('0470 12 34 56');
});

it('weigert een klant zonder naam via het formulier', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('openCreate')
        ->call('saveCustomer')
        ->assertHasErrors(['customerFormName' => 'required']);

    expect(Customer::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('bewerkt een werkadres met DDT via /klanten', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'street' => 'Kerkstraat',
        'postal_code' => '2000',
        'city' => 'Antwerpen',
    ]);

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('openLocationEdit', $location->id)
        ->assertSet('locationFormStreet', 'Kerkstraat')
        ->set('locationFormCity', 'Berchem')
        ->set('locationFormDdt', 'W123456789012')
        ->call('saveLocation')
        ->assertHasNoErrors();

    $fresh = $location->fresh();

    expect($fresh->city)->toBe('Berchem')
        ->and($fresh->contractual_relationship_reference)->toBe('W123456789012')
        ->and($fresh->customer_id)->toBe($customer->id);
});

it('maakt een werkadres met DDT aan via /klanten', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('openLocationCreate', $customer->id)
        ->assertSee('wpGoogleMapsUrlFor', false)
        ->set('locationFormStreet', 'Kerkstraat')
        ->set('locationFormPostalCode', '2000')
        ->set('locationFormCity', 'Antwerpen')
        ->set('locationFormDdt', 'W123456789012')
        ->call('saveLocation')
        ->assertHasNoErrors();

    $location = Location::where('tenant_id', $tenant->id)->sole();

    expect($location->customer_id)->toBe($customer->id)
        ->and($location->contractual_relationship_reference)->toBe('W123456789012');
});

it('weigert een ongeldige DDT op het werkadres-formulier', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('openLocationCreate', $customer->id)
        ->set('locationFormStreet', 'Kerkstraat')
        ->set('locationFormPostalCode', '2000')
        ->set('locationFormCity', 'Antwerpen')
        ->set('locationFormDdt', 'te-kort')
        ->call('saveLocation')
        ->assertHasErrors(['contractual_relationship_reference' => 'regex']);

    expect(Location::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('vult werkadres-velden automatisch bij het plakken van een Google Maps-adres', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->call('applyLocationAddressPaste', 'Marktstraat 61, 8301 Knokke-Heist')
        ->assertSet('locationFormStreet', 'Marktstraat')
        ->assertSet('locationFormHouseNumber', '61')
        ->assertSet('locationFormPostalCode', '8301')
        ->assertSet('locationFormCity', 'Knokke-Heist');

    // Dezelfde plak-flow op de "Nieuwe locatie"-popup (gedeelde trait).
    $facilityTenant = Tenant::factory()->create(['checkmate_mode' => false]);
    $facilityAdmin = User::factory()->admin()->for($facilityTenant)->create();

    Livewire::actingAs($facilityAdmin)
        ->test(LocationsIndex::class)
        ->call('applyLocationAddressPaste', 'Marktstraat 61, 8301 Knokke-Heist')
        ->assertSet('locationFormStreet', 'Marktstraat')
        ->assertSet('locationFormHouseNumber', '61')
        ->assertSet('locationFormPostalCode', '8301')
        ->assertSet('locationFormCity', 'Knokke-Heist');
});

it('parst Google Maps-adresvarianten (land-suffix, bus, NL-postcode) en laat niet-adressen ongemoeid', function () {
    expect(GoogleMapsAddressLine::tryParse('Marktstraat 61, 8301 Knokke-Heist, België'))
        ->toMatchArray([
            'street' => 'Marktstraat',
            'house_number' => '61',
            'postal_code' => '8301',
            'city' => 'Knokke-Heist',
        ]);

    expect(GoogleMapsAddressLine::tryParse('Kerkstraat 12 bus 3, 2000 Antwerpen'))
        ->toMatchArray([
            'street' => 'Kerkstraat',
            'house_number' => '12 bus 3',
            'postal_code' => '2000',
            'city' => 'Antwerpen',
        ]);

    expect(GoogleMapsAddressLine::tryParse('Kalvermarkt 1, 2511 CB Den Haag'))
        ->toMatchArray([
            'street' => 'Kalvermarkt',
            'house_number' => '1',
            'postal_code' => '2511 CB',
            'city' => 'Den Haag',
        ]);

    expect(GoogleMapsAddressLine::tryParse('knokke-heist'))->toBeNull()
        ->and(GoogleMapsAddressLine::tryParse('51.336167, 3.236306'))->toBeNull()
        ->and(GoogleMapsAddressLine::tryParse(''))->toBeNull();
});

it('linkt onderaan de klantenpagina naar de instructievideo in een nieuw tabblad', function () {
    $tenant = checkmateTenant();
    $admin = User::factory()->admin()->for($tenant)->create();

    Livewire::actingAs($admin)
        ->test(CustomersIndex::class)
        ->assertSee(__('customers.video_button'))
        ->assertSee('video/nl/werkadres.mp4')
        ->assertSee('target="_blank"', false);
});
