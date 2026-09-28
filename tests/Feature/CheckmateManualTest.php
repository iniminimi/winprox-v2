<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

function checkmateManualTenant(array $attrs = []): Tenant
{
    return Tenant::factory()->create(array_merge([
        'checkmate_mode' => true,
        'has_time_module' => true,
        'time_gps_visits' => true,
        'billing_plan' => 'checkmate',
        'billing_active_until' => now()->addMonth(),
    ], $attrs));
}

it('toont op de handleiding-hub enkel de checkmate-handleiding voor checkmate-tenants', function () {
    $tenant = checkmateManualTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $this->get('/manual')
        ->assertOk()
        ->assertSee(route('manual.checkmate'))
        ->assertDontSee(route('manual.general'))
        ->assertDontSee(route('manual.workers'))
        ->assertDontSee(route('manual.teamleaders'));
});

it('toont de gewone handleidingen voor facility-tenants', function () {
    $tenant = Tenant::factory()->create(['checkmate_mode' => false]);
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $this->get('/manual')
        ->assertOk()
        ->assertSee(route('manual.general'))
        ->assertSee(route('manual.workers'))
        ->assertSee(route('manual.teamleaders'))
        ->assertDontSee(route('manual.checkmate'));
});

it('redirect checkmate-tenants van de algemene handleiding naar de checkmate-handleiding', function () {
    $tenant = checkmateManualTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $this->get('/manual/general')->assertRedirect(route('manual.checkmate'));
    $this->get('/manual/workers')->assertRedirect(route('manual.checkmate'));
    $this->get('/manual/teamleaders')->assertRedirect(route('manual.checkmate'));
});

it('redirect niet-checkmate-tenants van de checkmate-handleiding naar de hub', function () {
    $tenant = Tenant::factory()->create(['checkmate_mode' => false]);
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $this->get('/manual/checkmate')->assertRedirect(route('manual.hub'));
});

it('rendert de checkmate-handleiding met checkmate-logo en beknopte hoofdstukken', function () {
    $tenant = checkmateManualTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $response = $this->get('/manual/checkmate')->assertOk();

    $response->assertSee('winprox_checkmate.png')
        ->assertDontSee('Winprox_logo_300.png')
        ->assertSee(__('manual.checkmate.cover.title'))
        // Compact: enkel checkmate-hoofdstukken, geen facility-hoofdstukken.
        ->assertSee('chapter-checkmate-dashboard')
        ->assertSee('chapter-checkmate-portal-visit')
        ->assertDontSee('chapter-issues-list')
        ->assertDontSee('chapter-locations-list')
        ->assertDontSee('chapter-esg-dashboard');
});

it('rendert de checkmate-handleiding in alle locales', function () {
    $tenant = checkmateManualTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    foreach (['nl', 'en', 'fr', 'de', 'es', 'it'] as $locale) {
        $this->get('/manual/checkmate?lang='.$locale)
            ->assertOk()
            ->assertSee('winprox_checkmate.png');
    }
});

it('ondersteunt de screenshot-toggle op de checkmate-handleiding', function () {
    $tenant = checkmateManualTenant();
    $admin = User::factory()->admin()->for($tenant)->create();
    $this->actingAs($admin);

    $this->get('/manual/checkmate?screenshots=0')
        ->assertOk()
        ->assertSee('wp-manual-root--no-screenshots');
});
