<?php

use App\Livewire\Platform\Tenants;
use App\Livewire\Platform\Users;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

it('toont de laatst toegevoegde tenant bovenaan op organisaties', function () {
    $super = User::factory()->superuser()->create();
    Tenant::factory()->create([
        'name' => 'AAA Oud',
        'created_at' => now()->subDay(),
    ]);
    Tenant::factory()->create([
        'name' => 'ZZZ Nieuw',
        'created_at' => now(),
    ]);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->assertSeeInOrder(['ZZZ Nieuw', 'AAA Oud']);
});

it('toont de laatst toegevoegde gebruiker bovenaan op platformgebruikers', function () {
    $super = User::factory()->superuser()->create([
        'name' => 'Super Oud',
        'created_at' => now()->subDays(2),
    ]);
    $tenant = Tenant::factory()->create();
    User::factory()->for($tenant)->create([
        'name' => 'Gebruiker Oud',
        'created_at' => now()->subDay(),
    ]);
    User::factory()->for($tenant)->create([
        'name' => 'Gebruiker Nieuw',
        'created_at' => now(),
    ]);

    Livewire::actingAs($super)
        ->test(Users::class)
        ->assertSeeInOrder(['Gebruiker Nieuw', 'Gebruiker Oud', 'Super Oud']);
});
