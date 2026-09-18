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

it('laat een nieuw clock point zonder startscherm-link aanmaken', function () {
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

it('slaat het vinkje Clock Point-link op startscherm gsm op', function () {
    [, $admin, $clockPoint] = homescreenClockPointSetup();

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->call('openEdit', $clockPoint->id)
        ->assertSet('homescreenShortcut', false)
        ->set('homescreenShortcut', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($clockPoint->fresh()->homescreen_shortcut)->toBeTrue();
});

it('toont het WinProx-icoon alleen als het clock point het vinkje aan heeft', function () {
    [, , $clockPoint] = homescreenClockPointSetup();

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertDontSeeHtml('data-wp-homescreen-install');

    $html = $this->get('/time/'.$clockPoint->qr_token)
        ->assertOk()
        ->assertDontSee('manifest.webmanifest', false)
        ->assertDontSee('data-wp-homescreen-install', false)
        ->getContent();

    expect($html)->toContain('<div wire:snapshot=')
        ->and($html)->not->toContain('<script wire:snapshot');

    $clockPoint->update(['homescreen_shortcut' => true]);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSeeHtml('data-wp-homescreen-install')
        ->assertSeeHtml('wp-homescreen-shortcut__add')
        ->assertSee(__('time.portal.homescreen.title'), false);

    $this->get('/time/'.$clockPoint->qr_token)
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('data-wp-homescreen-install', false);
});

it('opent de startscherm-hulp alleen als het vinkje aan staat', function () {
    [, , $clockPoint] = homescreenClockPointSetup();

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('openHomescreenHelp')
        ->assertSet('homescreenHelpOpen', false)
        ->assertDontSee(__('time.portal.homescreen.help_title'), false);

    $clockPoint->update(['homescreen_shortcut' => true]);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('openHomescreenHelp')
        ->assertSet('homescreenHelpOpen', true)
        ->assertSee(__('time.portal.homescreen.help_title'), false)
        ->call('closeHomescreenHelp')
        ->assertSet('homescreenHelpOpen', false);
});

it('verbergt het startscherm-icoon na aanmelden', function () {
    [$tenant, , $clockPoint] = homescreenClockPointSetup([
        'homescreen_shortcut' => true,
    ]);
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
        ->assertDontSeeHtml('data-wp-homescreen-install')
        ->assertDontSeeHtml('<h1 class="wp-page-title">'.e(__('time.portal.title')).'</h1>');
});

it('levert een webmanifest alleen als het clock point de startscherm-link aanbiedt', function () {
    [, , $clockPoint] = homescreenClockPointSetup();

    $this->get(route('public.time-portal.manifest', $clockPoint->qr_token))
        ->assertNotFound();

    $clockPoint->update(['homescreen_shortcut' => true]);

    $this->get(route('public.time-portal.manifest', $clockPoint->qr_token))
        ->assertOk()
        ->assertJsonPath('name', 'WinProx')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('background_color', '#ffffff')
        ->assertJsonPath('start_url', route('public.time-portal', $clockPoint->qr_token));
});
