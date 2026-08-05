<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('maakt alle beheers-navigatie bereikbaar via de app-shell', function () {
    $tenant = Tenant::factory()->create(['name' => 'Demo Facility']);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $routes = [
        'dashboard',
        'issues.index',
        'locations.index',
        'tasks.index',
        'calendar.index',
        'team.index',
        'settings.index',
        'subscription.index',
        'faq.index',
        'legal.index',
        'contact.index',
    ];

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('common.nav.settings'), false);

    foreach ($routes as $route) {
        $this->actingAs($user)
            ->get(route($route))
            ->assertOk()
            ->assertSee('Demo Facility');
    }
});

it('toont instellingen maar geen abonnement in de sidebar voor medewerkers', function () {
    $tenant = Tenant::factory()->create(['name' => 'Demo Facility']);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('subscription.index').'"', false)
        ->assertSee(__('common.nav.settings'), false)
        ->assertSee(__('common.nav.backoffice'), false)
        ->assertSee(__('team.nav.teams'), false)
        ->assertSee('href="'.route('team.index', ['section' => 'backoffice']).'"', false)
        ->assertSee('href="'.route('team.index', ['section' => 'teams']).'"', false);

    $this->actingAs($employee)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee(__('settings.style.title'), false);
});

it('opent team-pagina via Mensen → Backoffice en Teams section-links', function () {
    $tenant = Tenant::factory()->create(['name' => 'Demo Facility']);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $this->actingAs($admin)
        ->get(route('team.index', ['section' => 'backoffice']))
        ->assertOk()
        ->assertSee('id="backoffice"', false)
        ->assertSee('id="teams"', false);
});

it('toont submenu-items als bullets zonder herhaalde groep-iconen', function () {
    $tenant = Tenant::factory()->create(['name' => 'Demo Facility']);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $html = $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('wp-nav-link--sub')
        ->and($html)->toContain('wp-sidebar-accordion')
        ->and($html)->toContain('@toggle.capture="exclusive($event)"');
});
