<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('maakt alle beheers-navigatie bereikbaar via de app-shell', function () {
    $tenant = Tenant::factory()->create(['name' => 'Demo Facility']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $routes = [
        'dashboard',
        'issues.index',
        'issues.create',
        'locations.index',
        'tasks.index',
        'calendar.index',
        'team.index',
        'subscription.index',
        'faq.index',
        'legal.index',
        'contact.index',
    ];

    foreach ($routes as $route) {
        $this->actingAs($user)
            ->get(route($route))
            ->assertOk()
            ->assertSee('Demo Facility');
    }
});
