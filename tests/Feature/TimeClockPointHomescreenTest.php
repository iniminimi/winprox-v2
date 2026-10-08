<?php

declare(strict_types=1);

use App\Livewire\Public\TimePortal;
use App\Livewire\Time\ClockPointsIndex;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function homescreenClockPointSetup(array $clockPointAttrs = []): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $clockPoint = ClockPoint::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Magazijn',
    ], $clockPointAttrs));

    return [$tenant, $admin, $clockPoint];
}

it('laat een nieuw clock point aanmaken', function () {
    [, $admin] = homescreenClockPointSetup();

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->call('openCreate')
        ->set('name', 'Thuisstart')
        ->call('save')
        ->assertHasNoErrors();

    $created = ClockPoint::query()->where('name', 'Thuisstart')->first();
    expect($created)->not->toBeNull()
        ->and($created->homescreen_shortcut)->toBeFalse();
});

it('toont het WinProx-icoon op elk clock point', function () {
    [, , $clockPoint] = homescreenClockPointSetup();

    expect($clockPoint->homescreen_shortcut)->toBeFalse();

    $html = $this->get('/time/'.$clockPoint->qr_token)
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('data-wp-homescreen-install', false)
        ->getContent();

    expect($html)->toContain('<div wire:snapshot=')
        ->and($html)->not->toContain('<script wire:snapshot');

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSeeHtml('data-wp-homescreen-install')
        ->assertSeeHtml('wp-homescreen-shortcut__add')
        ->assertSee(__('time.portal.homescreen.title'), false);
});

it('opent de startscherm-hulp op elk clock point', function () {
    [, , $clockPoint] = homescreenClockPointSetup();

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('openHomescreenHelp')
        ->assertSet('homescreenHelpOpen', true)
        ->assertSee(__('time.portal.homescreen.help_title'), false)
        ->call('closeHomescreenHelp')
        ->assertSet('homescreenHelpOpen', false);
});

it('toont het startscherm-icoon ook na aanmelden', function () {
    [$tenant, , $clockPoint] = homescreenClockPointSetup();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSeeHtml('data-wp-homescreen-install')
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSeeHtml('data-wp-homescreen-install')
        ->assertDontSeeHtml('<h1 class="wp-page-title">'.e(__('time.portal.title')).'</h1>');
});

it('levert een webmanifest voor elk clock point', function () {
    [, , $clockPoint] = homescreenClockPointSetup();

    $this->get(route('public.time-portal.manifest', $clockPoint->qr_token))
        ->assertOk()
        ->assertJsonPath('name', 'WinProx')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('background_color', '#ffffff')
        ->assertJsonPath('start_url', route('public.time-portal.cp', $clockPoint->qr_token))
        ->assertJsonPath('id', route('public.time-portal.cp', $clockPoint->qr_token));
});
