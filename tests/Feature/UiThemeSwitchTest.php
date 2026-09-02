<?php

declare(strict_types=1);

use App\Enums\UiTheme;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

it('heeft Modern als default ui-stijl', function () {
    expect(UiTheme::default())->toBe(UiTheme::Modern)
        ->and(UiTheme::choices())->toBe([UiTheme::Modern, UiTheme::Simple, UiTheme::Dark]);
});

it('slaagt ui-stijl op via route voor ingelogde gebruiker', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'ui_theme' => 'simple',
    ]);

    $this->actingAs($user)
        ->get(route('ui-theme.switch', 'dark'))
        ->assertRedirect();

    expect($user->fresh()->ui_theme)->toBe('dark');
});

it('kan wisselen naar Modern', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'ui_theme' => 'simple',
    ]);

    $this->actingAs($user)
        ->get(route('ui-theme.switch', 'modern'))
        ->assertRedirect();

    expect($user->fresh()->ui_theme)->toBe('modern');
});

it('negeert onbekende ui-stijl in route', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'ui_theme' => 'simple',
    ]);

    $this->actingAs($user)
        ->get(route('ui-theme.switch', 'neon'))
        ->assertRedirect();

    expect($user->fresh()->ui_theme)->toBe('simple');
});

it('negeert verwijderde highres ui-stijl in route', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'ui_theme' => 'simple',
    ]);

    $this->actingAs($user)
        ->get(route('ui-theme.switch', 'highres'))
        ->assertRedirect();

    expect($user->fresh()->ui_theme)->toBe('simple');
});

it('stuurt gast door naar login bij ui-stijl route', function () {
    $this->get(route('ui-theme.switch', 'dark'))
        ->assertRedirect(route('login'));
});
