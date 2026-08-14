<?php

declare(strict_types=1);

use App\Livewire\Time\ClockPointsIndex;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Qr\ClockPointQrPackStickerEntries;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Tenancy;
use Livewire\Livewire;

it('maps clock point to printable sticker entry with name above and location below', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hal Noord',
    ]);
    $clockPoint = ClockPoint::factory()->forLocation($location)->create([
        'name' => 'Ingang A',
    ]);

    $entries = ClockPointQrPackStickerEntries::forClockPoint($clockPoint);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->unitLabel)->toBe('Ingang A')
        ->and($entries[0]->locationUnitLabel)->toBe('Ingang A')
        ->and($entries[0]->stickerNumber)->toBe('Hal Noord')
        ->and($entries[0]->pageHeadline)->toBe(__('time.clock_points.qr.subtitle'))
        ->and($entries[0]->reportUrl)->toBe($clockPoint->portalUrl());
});

it('omits below-label when clock point has no location', function () {
    $clockPoint = ClockPoint::factory()->create([
        'name' => 'Reception',
        'location_id' => null,
    ]);

    $entries = ClockPointQrPackStickerEntries::forClockPoint($clockPoint);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->locationUnitLabel)->toBe('Reception')
        ->and($entries[0]->stickerNumber)->toBe('')
        ->and($entries[0]->pageHeadline)->toBe(__('time.clock_points.qr.subtitle'));
});

it('clock-point qr-pack a6 download returns docx without time module', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    Tenancy::actAs($tenant->id);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort 1',
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($admin)->get(route('time.clock-points.qr-pack', [
        'clockPoint' => $clockPoint,
        'template' => 'a6_print',
    ]));

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toContain('winprox-qr-clock-A6-');

    $zip = new ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'wp-docx-clock-a6-');
    file_put_contents($tmp, $response->streamedContent());
    expect($zip->open($tmp))->toBeTrue();
    $documentXml = $zip->getFromName('word/document.xml');
    $mediaCount = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (is_string($name) && str_starts_with($name, 'word/media/')) {
            $mediaCount++;
        }
    }
    $zip->close();
    @unlink($tmp);

    expect($documentXml)->toBeString()
        ->and($documentXml)->toContain('w:pgSz')
        ->and($mediaCount)->toBeGreaterThanOrEqual(1);
});

it('rejects non-printable clock-point qr-pack templates', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.clock-points.qr-pack', [
            'clockPoint' => $clockPoint,
            'template' => 'avery_55x55_s',
        ]))
        ->assertUnprocessable();
});

it('keeps browser print page without word format buttons', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.clock-points.qr', $clockPoint))
        ->assertOk()
        ->assertSee(__('time.clock_points.qr.print'), false)
        ->assertDontSee(__('common.qr.pack_heading'), false)
        ->assertDontSee('qr-pack?template=a6_print', false);
});

it('opens clock-point qr cluster modal with print pack and renew choices', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort Zuid',
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->assertSee(__('common.qr.button'), false)
        ->assertDontSee(__('common.qr.print'), false)
        ->assertDontSee(__('time.clock_points.qr.renew'), false)
        ->call('openQrPackModal', $clockPoint->id)
        ->assertSet('showQrPackModal', true)
        ->assertSet('qrPackClockPointId', $clockPoint->id)
        ->assertSee(__('common.qr.modal_title'), false)
        ->assertSee($clockPoint->name, false)
        ->assertSee(__('common.qr.print'), false)
        ->assertSee(__('common.qr.pack_heading'), false)
        ->assertSee(__('time.clock_points.qr.renew'), false)
        ->assertSee(__('common.qr.formats.a6_print.title'), false)
        ->assertSee(__('common.qr.formats.a6_print.size'), false)
        ->assertSee(__('common.qr.formats.a5_print.title'), false)
        ->assertSee(__('common.qr.formats.a4_print.title'), false)
        ->assertSee(route('time.clock-points.qr', $clockPoint), false)
        ->assertSee('qr-pack?template=a6_print', false)
        ->assertSeeHtml('class="wp-modal"')
        ->assertSeeHtml('window.wpQrPackPicker(')
        ->assertDontSeeHtml('<button type="button" class="wp-issue-row"><p');
});
