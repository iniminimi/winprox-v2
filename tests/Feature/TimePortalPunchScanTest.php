<?php

declare(strict_types=1);

use App\Actions\Time\ClockInAction;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
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

it('klokt meteen in zodra de worker op een verse QR-scan is aangemeld', function () {
    [$tenant, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint)
        ->assertSet('flashMessage', __('time.portal.clock.clocked_in_at_tenant', [
            'tenant' => $tenant->name,
            'time' => now()->format('H:i'),
        ]))
        ->assertDontSee(__('time.portal.clock.scan_to_clock_in'), false)
        ->assertDontSeeHtml('@click="withGps(\'clockIn\')"');

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
        ->assertSet('flashMessage', __('time.portal.errors.already_clocked_in'))
        ->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue();
});

it('laat uitklokken na een nieuwe QR-scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('clockOut')
        ->assertSet('flashMessage', '')
        ->assertSee(__('time.portal.clock.clocked_out_at', ['time' => now()->format('H:i')]), false)
        ->assertDontSee(__('time.portal.clock.not_clocked_in'), false)
        ->assertDontSee(__('time.portal.clock.scan_to_clock_in'), false);

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('toont na een nieuwe scan opnieuw inklokken bij een afgesloten dienst', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('clockOut')
        ->assertSet('flashMessage', '');

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
        ->assertSee(__('time.portal.clock.not_clocked_in'), false)
        ->assertSee(__('time.portal.clock.scan_to_clock_in'), false)
        ->assertDontSeeHtml('@click="withGps(\'clockIn\')"');

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('prikt niet in wanneer de scan-grant verlopen is', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token]);

    $this->travel(11)->minutes();

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
        ->assertSet('flashMessage', __('time.portal.clock.clocked_in_at_tenant', [
            'tenant' => $clockPoint->tenant->name,
            'time' => now()->format('H:i'),
        ]));

    $portal->call('startBreak')
        ->assertSet('flashMessage', __('time.portal.break_started'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->first()?->openBreak)->not->toBeNull();
});

it('verplaatst een open dienst meteen bij aanmelden op een andere Clock Point', function () {
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
        ->assertSet('flashMessage', __('time.portal.transferred'));

    $portal->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect((int) WorkShift::query()->where('worker_id', $worker->id)->open()->value('presence_clock_point_id'))
        ->toBe($clockB->id);
});
