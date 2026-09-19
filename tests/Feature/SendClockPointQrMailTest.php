<?php

declare(strict_types=1);

use App\Actions\Time\SendClockPointQrMailAction;
use App\Enums\EmailUnsubscribeSource;
use App\Livewire\Time\ClockPointsIndex;
use App\Mail\ClockPointQrMail;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\EmailUnsubscribe;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }
});

afterEach(fn () => Tenancy::forget());

function clockPointQrMailSetup(array $clockPoint = [], array $user = []): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true, 'name' => 'Acme Facility']);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hal Noord',
    ]);
    $point = ClockPoint::factory()->forLocation($location)->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Aanmelden',
        'is_active' => true,
    ], $clockPoint));
    $admin = User::factory()->admin()->create(array_merge([
        'tenant_id' => $tenant->id,
        'locale' => 'nl',
    ], $user));

    return [$tenant, $point, $admin];
}

it('sends clock point qr and portal url from the qr modal', function () {
    Mail::fake();
    [$tenant, $clockPoint, $admin] = clockPointQrMailSetup();

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->call('openQrPackModal', $clockPoint->id)
        ->assertSee(__('time.clock_points.qr.email.heading'), false)
        ->set('qrMailEmail', 'Worker@Site.test')
        ->call('sendQrMail')
        ->assertHasNoErrors()
        ->assertSet('qrMailEmail', '')
        ->assertSet('qrMailFlash', __('time.clock_points.qr.email.sent'));

    Mail::assertSent(ClockPointQrMail::class, function (ClockPointQrMail $mail) use ($clockPoint, $tenant) {
        $html = $mail->render();
        $locale = 'nl';

        $mail->assertHasSubject(trans('mail.clock_point_qr.subject', ['tenant' => $tenant->name], $locale));

        expect($mail->hasTo('worker@site.test'))->toBeTrue()
            ->and($html)->toContain($clockPoint->portalUrl())
            ->and($html)->toContain('/time/')
            ->and($html)->toContain('cid:clock-point-qr.png')
            ->and($html)->toContain(trans('mail.clock_point_qr.cta', [], $locale))
            ->and($html)->toContain('#059669')
            ->and($html)->toContain('Hal Noord')
            ->and($mail->envelope()->subject)->not->toContain('Worker')
            ->and($mail->envelope()->subject)->not->toContain($clockPoint->portalUrl());

        return true;
    });

    expect(AuditLog::query()->where('action', 'clock_point.qr_mailed')->where('model_id', $clockPoint->id)->exists())->toBeTrue();
});

it('rejects a bounced address and does not send', function () {
    Mail::fake();
    [, $clockPoint, $admin] = clockPointQrMailSetup();

    EmailUnsubscribe::query()->create([
        'email' => 'dead@site.test',
        'source' => EmailUnsubscribeSource::Undeliverable,
        'unsubscribed_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->call('openQrPackModal', $clockPoint->id)
        ->set('qrMailEmail', 'dead@site.test')
        ->call('sendQrMail')
        ->assertHasErrors(['qrMailEmail']);

    Mail::assertNothingSent();
});

it('rejects an inactive clock point', function () {
    Mail::fake();
    [, $clockPoint, $admin] = clockPointQrMailSetup(['is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->call('openQrPackModal', $clockPoint->id)
        ->set('qrMailEmail', 'worker@site.test')
        ->call('sendQrMail')
        ->assertHasErrors(['qrMailEmail']);

    Mail::assertNothingSent();
});

it('rate limits repeated clock point qr mails', function () {
    Mail::fake();
    [$tenant, $clockPoint, $admin] = clockPointQrMailSetup();

    $key = SendClockPointQrMailAction::rateLimitKey((int) $tenant->id, (int) $admin->id);
    for ($i = 0; $i < SendClockPointQrMailAction::MAX_PER_WINDOW; $i++) {
        RateLimiter::hit($key, SendClockPointQrMailAction::WINDOW_SECONDS);
    }

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->call('openQrPackModal', $clockPoint->id)
        ->set('qrMailEmail', 'worker@site.test')
        ->call('sendQrMail')
        ->assertHasErrors(['qrMailEmail']);

    Mail::assertNothingSent();
});
