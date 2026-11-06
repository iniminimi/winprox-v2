<?php

declare(strict_types=1);

use App\Livewire\BackofficeAppInstall;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('levert een webmanifest voor het beheer', function () {
    $this->get(route('backoffice.manifest'))
        ->assertOk()
        ->assertJsonPath('name', __('common.pwa.name'))
        ->assertJsonPath('short_name', __('common.pwa.short_name'))
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', route('dashboard'))
        ->assertJsonPath('id', url('/backoffice'))
        ->assertJsonPath('scope', rtrim(url('/'), '/').'/')
        ->assertJsonPath('handle_links', 'not-preferred')
        ->assertJsonPath('launch_handler.client_mode', 'navigate-existing')
        ->assertJsonPath('related_applications.0.platform', 'webapp')
        ->assertJsonPath('related_applications.0.url', route('backoffice.manifest'))
        ->assertJsonPath('related_applications.0.id', url('/backoffice'))
        ->assertJsonPath('icons.0.purpose', 'any')
        ->assertJsonPath('icons.2.purpose', 'maskable');
});

it('toont de bureaublad-knop in het beheer', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('backoffice.manifest'), false)
        ->assertSee('data-wp-backoffice-install', false)
        ->assertSee(__('common.pwa.install'), false)
        ->assertSee('sw-portal.js', false);
});

it('opent de uitleg als de browser geen installatievraag toont', function () {
    Livewire::test(BackofficeAppInstall::class)
        ->assertSeeHtml('data-wp-backoffice-install')
        ->call('openHelp')
        ->assertSet('helpOpen', true)
        ->assertSee(__('common.pwa.help_title'), false)
        ->call('closeHelp')
        ->assertSet('helpOpen', false);
});
