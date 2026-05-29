<?php

use App\Livewire\Auth\Login;
use App\Livewire\Issues\Index;
use App\Models\Issue;
use App\Models\Tenant;
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
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('toont de moderatie-blur voor een niet-goedgekeurde melding', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => null,
        'description' => 'Gevoelige inhoud die geblurd moet blijven',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSeeHtml('wp-pending-review')
        ->assertSee(__('issues.pending_review'));
});

it('keurt een melding goed vanuit de lijst', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => null]);

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

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
        'approved_by' => $user->id,
        'description' => 'Goedgekeurde, zichtbare inhoud',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertDontSeeHtml('wp-pending-review')
        ->assertSee('Goedgekeurde, zichtbare inhoud');
});
