<?php

declare(strict_types=1);

use App\Actions\Time\ClockInAction;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Portal\ClockPointScanGrant;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function punchScanTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'punch-scan-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    return [$tenant, $clockPoint, $worker];
}

function signInPunchScanWorker(ClockPoint $clockPoint): mixed
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');
}

it('klokt niet in bij aanmelden en wel na een tik op inklokken', function () {
    [$tenant, $clockPoint, $worker] = punchScanTenant();

    $portal = signInPunchScanWorker($clockPoint)
        ->assertSee(__('time.portal.now.status_out'), false)
        ->assertDontSee(__('time.portal.clock.scan_to_clock_in'), false);

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse()
        ->and(ClockPointScanGrant::isValid($clockPoint->id))->toBeTrue();

    $portal->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clock.clocked_in_at_tenant', [
            'tenant' => $tenant->name,
            'time' => now()->format('H:i'),
        ]))
        ->assertDontSee(__('time.portal.clock.scan_to_clock_in'), false);

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue()
        ->and(ClockPointScanGrant::isValid($clockPoint->id))->toBeFalse();
});

it('prikt niet bij alleen identificeren zonder verificatie', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker');

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse()
        ->and(ClockPointScanGrant::isValid($clockPoint->id))->toBeTrue();
});

it('weigert een tweede prik op dezelfde open tab zonder nieuwe scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint)
        ->call('clockIn')
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.errors.already_clocked_in'))
        ->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue();
});

it('laat uitklokken na een nieuwe QR-scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint)->call('clockIn');

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.clock.clocked_out_at', ['time' => now()->format('H:i')]))
        ->assertSee(__('time.portal.clock.clocked_out_at', ['time' => now()->format('H:i')]), false)
        ->assertDontSee(__('time.portal.clock.not_clocked_in'), false)
        ->assertDontSee(__('time.portal.clock.scan_to_clock_in'), false);

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('toont na een nieuwe scan opnieuw inklokken bij een afgesloten dienst', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint)->call('clockIn');

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.clock.clocked_out_at', ['time' => now()->format('H:i')]));

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSee(__('time.portal.clock.clocked_out_at', ['time' => now()->format('H:i')]), false)
        ->assertSee(__('time.portal.clock.in'), false)
        ->assertDontSee(__('time.portal.clock.not_clocked_in'), false)
        ->assertDontSee(__('time.portal.clock.scan_to_clock_in'), false);

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('toont een korte scan-hint als de grant opgebruikt is en er nog geen dienst is', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token]);
    ClockPointScanGrant::consume($clockPoint->id);

    $portal
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('$refresh')
        ->assertSee(__('time.portal.now.status_out'), false)
        ->assertSee(__('time.portal.clock.in'), false)
        ->assertSeeHtml('wpPortalQrScanner')
        ->assertSeeHtml('left: 0', false);

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('toont de in-portal scan-knop wanneer er geen scan-grant is', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token]);
    ClockPointScanGrant::consume($clockPoint->id);

    $portal
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('$refresh')
        ->assertSee(__('time.portal.clock.in'), false)
        ->assertSeeHtml('wpPortalQrScanner');
});

it('verbergt de scan-knop zolang er een geldige scan-grant is', function () {
    [, $clockPoint] = punchScanTenant();

    signInPunchScanWorker($clockPoint)
        ->assertSee(__('time.portal.clock.in'), false)
        ->assertSeeHtml('x-show="direct || left > 0"', false)
        ->assertSeeHtml('x-show="!direct && left <= 0"', false);
});

it('laat een prik nog toe binnen twee minuten na de scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = signInPunchScanWorker($clockPoint);

    $this->travel(90)->seconds();

    $portal->call('clockIn');

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue()
        ->and(ClockPointScanGrant::isValid($clockPoint->id))->toBeFalse();
});

it('prikt niet in wanneer de scan-grant verlopen is', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token]);

    $this->travel(3)->minutes();

    $portal
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('laat pauzes zonder nieuwe scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = signInPunchScanWorker($clockPoint)
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clock.clocked_in_at_tenant', [
            'tenant' => $clockPoint->tenant->name,
            'time' => now()->format('H:i'),
        ]));

    $portal->call('startBreak')
        ->assertSet('flashMessage', __('time.portal.break_started'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->first()?->openBreak)->not->toBeNull();
});

it('verplaatst een open dienst na een scan op een andere Clock Point', function () {
    [$tenant, $clockA, $worker] = punchScanTenant();
    $clockB = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'punch-scan-b-'.$tenant->id,
    ]);

    app(ClockInAction::class)->handle($worker, $clockA);

    $portal = Livewire::test(TimePortal::class, ['token' => $clockB->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('transferToThisClockPoint')
        ->assertSet('flashMessage', __('time.portal.transferred'));

    $portal->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect((int) WorkShift::query()->where('worker_id', $worker->id)->open()->value('presence_clock_point_id'))
        ->toBe($clockB->id);
});

it('behandelt een tweede Clock Point op dezelfde vestiging niet als elders', function () {
    [$tenant, , $worker] = punchScanTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $clockA = ClockPoint::factory()->forLocation($location)->create([
        'qr_token' => 'same-site-a-'.$tenant->id,
    ]);
    $clockB = ClockPoint::factory()->forLocation($location)->create([
        'qr_token' => 'same-site-b-'.$tenant->id,
    ]);

    app(ClockInAction::class)->handle($worker, $clockA);

    Livewire::test(TimePortal::class, ['token' => $clockB->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertDontSee(__('time.portal.clock.elsewhere_body'), false)
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.errors.already_clocked_in'));

    $shift = WorkShift::query()->where('worker_id', $worker->id)->open()->first();
    expect((int) $shift->presence_clock_point_id)->toBe($clockA->id)
        ->and($shift->location_hops)->toBeEmpty();
});

it('blijft een open dienst op een andere vestiging als elders markeren', function () {
    [$tenant, , $worker] = punchScanTenant();
    $locA = Location::factory()->create(['tenant_id' => $tenant->id]);
    $locB = Location::factory()->create(['tenant_id' => $tenant->id]);
    $clockA = ClockPoint::factory()->forLocation($locA)->create([
        'qr_token' => 'diff-site-a-'.$tenant->id,
    ]);
    $clockB = ClockPoint::factory()->forLocation($locB)->create([
        'qr_token' => 'diff-site-b-'.$tenant->id,
    ]);

    app(ClockInAction::class)->handle($worker, $clockA);

    Livewire::test(TimePortal::class, ['token' => $clockB->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSee(__('time.portal.clock.elsewhere_body'), false);
});

it('behandelt een Clock Point zonder vestiging via de punt-vergelijking', function () {
    [$tenant, , $worker] = punchScanTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $clockLocated = ClockPoint::factory()->forLocation($location)->create([
        'qr_token' => 'located-'.$tenant->id,
    ]);
    $clockUnlocated = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'unlocated-'.$tenant->id,
    ]);

    app(ClockInAction::class)->handle($worker, $clockLocated);

    Livewire::test(TimePortal::class, ['token' => $clockUnlocated->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSee(__('time.portal.clock.elsewhere_body'), false);
});

it('geeft een scan op de sticker en niet op de aanmeldlink', function () {
    [, $clockPoint] = punchScanTenant();
    $clockPoint->update(['qr_token' => str_repeat('b', 32)]);

    $this->get(route('public.time-portal.cp', $clockPoint->qr_token))->assertOk();
    expect(ClockPointScanGrant::isValid($clockPoint->id))->toBeFalse();

    $this->get(route('public.time-portal', $clockPoint->qr_token))->assertOk();
    expect(ClockPointScanGrant::isValid($clockPoint->id))->toBeTrue();
});

it('laat checkmate inklokken zonder scan', function () {
    [$tenant, $clockPoint, $worker] = punchScanTenant();
    $tenant->update(['checkmate_mode' => true]);

    $portal = signInPunchScanWorker($clockPoint);
    ClockPointScanGrant::consume($clockPoint->id);

    $portal->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clock.clocked_in_at_tenant', [
            'tenant' => $tenant->name,
            'time' => now()->format('H:i'),
        ]));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue();
});
