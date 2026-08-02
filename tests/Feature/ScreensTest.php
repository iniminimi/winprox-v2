<?php

use App\Livewire\Auth\Login;
use App\Livewire\Issues\Index;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('logt een gebruiker in en stuurt door naar het dashboard', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
});

it('weigert verkeerde inloggegevens', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'fout-wachtwoord')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee('video/assistant_attention.mp4', false)
        ->assertSee('wp-page-icon--assistant', false)
        ->assertSee(__('auth.errors.failed'), false);

    expect(auth()->check())->toBeFalse();
});

it('toont het logo op de loginpagina zolang er geen fout is', function () {
    Livewire::test(Login::class)
        ->assertDontSee('video/assistant_attention.mp4', false)
        ->assertSee('Winprox_logo', false);
});

it('toont beheerschermen NOOIT geblurd, ook niet voor een niet-goedgekeurde melding', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'approved_at' => null,
        'description' => 'Niet-goedgekeurde inhoud, onverkort zichtbaar voor beheer',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertDontSeeHtml('wp-pending-review')
        ->assertSee('Niet-goedgekeurde inhoud, onverkort zichtbaar voor beheer');
});

it('keurt een melding goed vanuit de lijst', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'approved_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('approve', $issue->id)
        ->assertHasNoErrors();

    expect($issue->fresh()->isApproved())->toBeTrue()
        ->and($issue->fresh()->approved_by)->toBe($user->id);
});

it('toont geen blur voor een goedgekeurde melding', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'approved_at' => now(),
        'approved_by' => $user->id,
        'description' => 'Goedgekeurde, zichtbare inhoud',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertDontSeeHtml('wp-pending-review')
        ->assertSee('Goedgekeurde, zichtbare inhoud');
});
