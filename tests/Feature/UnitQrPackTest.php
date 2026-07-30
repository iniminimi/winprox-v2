<?php

declare(strict_types=1);

use App\Livewire\Locations\Show;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Qr\LocationQrPackStickerEntries;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\UnitPortalUrl;
use App\Support\Tenancy;
use Livewire\Livewire;

it('maps a single unit to one sticker entry via forUnit', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Magazijn Zuid',
    ]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Vergaderlokaal',
        'qr_token' => 'unit-token-abc',
    ]);

    $entries = LocationQrPackStickerEntries::forUnit($unit->fresh(['location', 'qrCodes']));

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->locationUnitLabel)->toBe('Magazijn Zuid - Vergaderlokaal')
        ->and($entries[0]->reportUrl)->toBe(UnitPortalUrl::forUnit($unit->fresh()));
});

it('unit qr-pack a6 download returns docx', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Poort 1',
        'qr_token' => 'unit-pack-token',
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($admin)->get(route('units.qr-pack', [
        'unit' => $unit,
        'template' => 'a6_print',
    ]));

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toContain('winprox-qr-unit-A6-');

    $zip = new ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'wp-docx-unit-a6-');
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

it('rejects non-printable unit qr-pack templates', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'qr_token' => 'unit-pack-reject',
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('units.qr-pack', [
            'unit' => $unit,
            'template' => 'avery_55x55_s',
        ]))
        ->assertUnprocessable();
});

it('keeps unit browser print page without word format buttons', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'qr_token' => 'unit-browser-print',
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('units.qr', $unit))
        ->assertOk()
        ->assertDontSee(__('locations.qr_pack.formats.a6_print.title'), false)
        ->assertDontSee('qr-pack?template=a6_print', false);
});

it('opens unit qr pack modal with a6 a5 a4 choices', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Vergaderlokaal',
        'qr_token' => 'unit-modal-token',
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openUnitQrPackModal', $unit->id)
        ->assertSet('showUnitQrPackModal', true)
        ->assertSet('unitQrPackUnitId', $unit->id)
        ->assertSee(__('locations.unit_qr_pack.modal_title'), false)
        ->assertSee(__('locations.qr_pack.formats.a6_print.title'), false)
        ->assertSee(__('locations.qr_pack.formats.a5_print.title'), false)
        ->assertSee(__('locations.qr_pack.formats.a4_print.title'), false)
        ->assertSee('qr-pack?template=a6_print', false);
});
