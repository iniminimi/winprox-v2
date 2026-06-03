<?php

use App\Actions\Search\SearchTenantGlobalAction;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Search\GlobalSearchTerms;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('splitst zoekopdrachten in losse woorden', function () {
    expect(GlobalSearchTerms::fromQuery('Boormachine 001'))->toBe(['Boormachine', '001'])
        ->and(GlobalSearchTerms::fromQuery('  dominique   schaepdrijver '))->toBe(['dominique', 'schaepdrijver']);
});

it('vindt units en workers op meerdere woorden via globale zoekactie', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Atelier']);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Boormachine 001',
        'description' => 'Serienummer 34962',
        'is_active' => true,
    ]);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Techniek']);
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Dominique',
        'last_name' => 'Schaepdrijver',
        'is_active' => true,
    ]);

    $results = app(SearchTenantGlobalAction::class)->handle($user, $tenant->id, 'Boormachine 001');

    expect($results->get('units'))->not->toBeNull()
        ->and($results->get('units')->first()['title'])->toBe('Boormachine 001')
        ->and($results->get('units')->first()['url'])->toContain('unit=')
        ->and($results->get('units')->first()['url'])->toContain('Boormachine');

    $workerResults = app(SearchTenantGlobalAction::class)->handle($user, $tenant->id, 'Dominique Schaepdrijver');

    expect($workerResults->get('workers'))->not->toBeNull()
        ->and($workerResults->get('workers')->first()['title'])->toBe('Dominique Schaepdrijver')
        ->and($workerResults->get('workers')->first()['url'])->toContain('worker=');
});

it('toont globale zoekresultaten in livewire component', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift 2',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\GlobalSearch::class)
        ->set('query', 'Lift 2')
        ->assertSee('Lift 2');
});

it('vindt FAQ-items op deelwoorden en opent het juiste onderwerp', function () {
    app()->setLocale('nl');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    Tenancy::actAs($tenant->id);

    $results = app(SearchTenantGlobalAction::class)->handle($user, $tenant->id, 'proef');

    expect($results->get('faq'))->not->toBeNull()
        ->and($results->get('faq')->first()['url'])->toContain('open=');

    Livewire::actingAs($user)
        ->withQueryParams(['open' => 'pricing'])
        ->test(\App\Livewire\Pages\Faq::class)
        ->assertSet('openSlug', 'pricing');
});
