<?php

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('exports filtered issues as csv for the tenant', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Lekkage waterleiding',
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);
    Issue::factory()->create([
        'tenant_id' => $other->id,
        'description' => 'Andere tenant',
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('issues.export', ['q' => 'Lekkage']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Lekkage waterleiding')
        ->and($response->streamedContent())->not->toContain('Andere tenant');
});

it('renders issues print view for filtered set', function () {
    $tenant = Tenant::factory()->create(['name' => 'Test Org']);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Printbare melding',
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('issues.print'))
        ->assertOk()
        ->assertSee('Printbare melding')
        ->assertSee('Test Org')
        ->assertSee(__('issues.card.kind_nr', ['nr' => Issue::query()->where('tenant_id', $tenant->id)->value('id')]))
        ->assertDontSee('<table>', false);
});

it('isolates task export by tenant', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
    ]);
    $foreignIssue = Issue::factory()->create([
        'tenant_id' => $other->id,
        'approved_at' => now(),
    ]);

    \App\Models\Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'description' => 'Eigen taak export',
        'status' => TaskStatus::New,
    ]);
    \App\Models\Task::factory()->create([
        'tenant_id' => $other->id,
        'issue_id' => $foreignIssue->id,
        'description' => 'Vreemde taak',
        'status' => TaskStatus::New,
    ]);

    $response = $this->actingAs($user)->get(route('tasks.export'));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Eigen taak export')
        ->and($response->streamedContent())->not->toContain('Vreemde taak');
});

it('exports unit measurements csv for the tenant', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $field = \App\Models\UnitMeasureField::factory()->string()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kilometerstand',
    ]);
    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = \App\Models\Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    \App\Models\UnitMeasurement::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'location_id' => $location->id,
        'unit_measure_field_id' => $field->id,
        'value_numeric' => null,
        'value_string' => 'Eigen unitmeting',
    ]);
    \App\Models\UnitMeasurement::factory()->create([
        'tenant_id' => $other->id,
        'value_string' => 'Vreemde unitmeting',
    ]);

    $response = $this->actingAs($user)->get(route('unit-measurements.export'));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Eigen unitmeting')
        ->and($response->streamedContent())->not->toContain('Vreemde unitmeting');
});

it('prints unit measurements with card layout', function () {
    $tenant = Tenant::factory()->create(['name' => 'Meet Org']);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $field = \App\Models\UnitMeasureField::factory()->string()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Statusveld',
    ]);
    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = \App\Models\Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    \App\Models\UnitMeasurement::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'location_id' => $location->id,
        'unit_measure_field_id' => $field->id,
        'value_numeric' => null,
        'value_string' => 'Printbare unitmeting',
    ]);

    $this->actingAs($user)
        ->get(route('unit-measurements.print'))
        ->assertOk()
        ->assertSee('Printbare unitmeting')
        ->assertSee('Meet Org')
        ->assertDontSee('<table>', false);
});

it('exports filtered reservations as csv for the tenant', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = \App\Models\Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $otherUnit = \App\Models\Unit::factory()->create(['tenant_id' => $other->id]);

    \App\Models\Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'guest_first_name' => 'Anna',
        'guest_last_name' => 'Eigen',
        'guest_email' => 'anna@example.com',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(2),
        'confirmed_at' => now(),
        'confirm_token' => 'confirm-own-'.uniqid(),
        'manage_token' => 'manage-own-'.uniqid(),
    ]);
    \App\Models\Reservation::query()->create([
        'tenant_id' => $other->id,
        'unit_id' => $otherUnit->id,
        'guest_first_name' => 'Bert',
        'guest_last_name' => 'Vreemd',
        'guest_email' => 'bert@example.com',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(2),
        'confirmed_at' => now(),
        'confirm_token' => 'confirm-other-'.uniqid(),
        'manage_token' => 'manage-other-'.uniqid(),
    ]);

    $response = $this->actingAs($user)->get(route('reservations.export', ['status' => 'all']));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Anna Eigen')
        ->and($response->streamedContent())->not->toContain('Bert Vreemd');
});

it('prints reservations with card layout', function () {
    $tenant = Tenant::factory()->create(['name' => 'Reserve Org']);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = \App\Models\Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Vergaderzaal A',
    ]);

    \App\Models\Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'guest_first_name' => 'Carla',
        'guest_last_name' => 'Print',
        'guest_email' => 'carla@example.com',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(2),
        'confirmed_at' => now(),
        'confirm_token' => 'confirm-print-'.uniqid(),
        'manage_token' => 'manage-print-'.uniqid(),
    ]);

    $this->actingAs($user)
        ->get(route('reservations.print', ['status' => 'all']))
        ->assertOk()
        ->assertSee('Carla Print')
        ->assertSee('Vergaderzaal A')
        ->assertSee('Reserve Org')
        ->assertDontSee('<table>', false);
});
