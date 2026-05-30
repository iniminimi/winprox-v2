<?php

use App\Livewire\Locations\Index as LocationIndex;
use App\Livewire\Locations\Show as LocationShow;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Tenancy;
use App\Support\Units\UnitBulkNaming;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('dashboard KPI links return 200', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('locations.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('issues.index', ['status' => 'new']))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('tasks.index', ['status' => 'in_progress']))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('briefing.print'))
        ->assertOk();
});

it('finds locations by house number in search', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Magazijn Zuid',
        'house_number' => '42B',
        'is_active' => true,
    ]);
    Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hal Noord',
        'house_number' => '7',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(LocationIndex::class)
        ->set('search', '42B')
        ->assertSee('Magazijn Zuid')
        ->assertDontSee('Hal Noord');
});

it('weigert een locatie zonder naam en zonder volledig adres', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(LocationIndex::class)
        ->call('openCreate')
        ->set('name', '')
        ->set('street', 'Industrieweg')
        ->set('postal_code', '')
        ->set('city', 'Gent')
        ->call('save')
        ->assertHasErrors(['name']);

    expect(Location::count())->toBe(0);
});

it('maakt een locatie aan met alleen straat postcode en plaats', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(LocationIndex::class)
        ->call('openCreate')
        ->set('name', '')
        ->set('street', 'Industrieweg')
        ->set('postal_code', '9000')
        ->set('city', 'Gent')
        ->set('country_code', '')
        ->call('save')
        ->assertHasNoErrors();

    $location = Location::where('street', 'Industrieweg')->first();

    expect($location)->not->toBeNull()
        ->and($location->name)->toBe('Industrieweg')
        ->and($location->country_code)->toBe('BE');
});

it('bewaart locatiegegevens via de bewerk-modal op detail', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Oude naam',
        'street' => 'Oude straat',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    Livewire::actingAs($user)
        ->test(LocationShow::class, ['location' => $location])
        ->call('openEditLocation')
        ->set('locationFormName', 'Nieuwe naam')
        ->set('locationFormStreet', 'Nieuwstraat')
        ->set('locationFormHouseNumber', '10')
        ->set('locationFormPostalCode', '2000')
        ->set('locationFormCity', 'Antwerpen')
        ->set('locationFormCountryCode', 'BE')
        ->set('locationFormNotes', 'Interne notitie')
        ->call('saveLocation')
        ->assertHasNoErrors()
        ->assertSet('showLocationModal', false);

    $location->refresh();

    expect($location->name)->toBe('Nieuwe naam')
        ->and($location->street)->toBe('Nieuwstraat')
        ->and($location->house_number)->toBe('10')
        ->and($location->postal_code)->toBe('2000')
        ->and($location->city)->toBe('Antwerpen')
        ->and($location->notes)->toBe('Interne notitie');
});

it('creates a location via Livewire', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(LocationIndex::class)
        ->call('openCreate')
        ->set('name', 'Hal A')
        ->set('street', 'Industrieweg')
        ->set('house_number', '12')
        ->set('postal_code', '9000')
        ->set('city', 'Gent')
        ->set('country_code', 'BE')
        ->call('save')
        ->assertHasNoErrors();

    $location = Location::where('name', 'Hal A')->first();

    expect($location)->not->toBeNull()
        ->and($location->street)->toBe('Industrieweg')
        ->and($location->location_qr_token)->not->toBeEmpty();
});

it('bulk creates units on location show', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Site Bulk']);

    Livewire::actingAs($user)
        ->test(LocationShow::class, ['location' => $location])
        ->call('openBulkModal')
        ->set('bulkFloors', '2')
        ->set('bulkRoomsPerFloor', '1')
        ->set('bulkScheme', UnitBulkNaming::SCHEME_COMPACT_2)
        ->set('bulkPrefix', 'Machine')
        ->call('createBulk')
        ->assertHasNoErrors();

    expect($location->units()->count())->toBe(2)
        ->and($location->units()->pluck('name')->all())->toBe(['Machine 01', 'Machine 11']);
});

it('toont geen QR-stickerblad-download zonder units', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Leeg']);

    Livewire::actingAs($user)
        ->test(LocationShow::class, ['location' => $location])
        ->assertDontSee(__('locations.qr_pack_download'));

    if (QrCodePngWriter::canGenerate()) {
        $this->actingAs($user)
            ->get(route('locations.qr-pack', $location))
            ->assertNotFound();
    }
});

it('qr-pack download returns docx when GD available', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hal A',
    ]);
    $location->units()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Machine 12',
        'qr_token' => 'qr-facility-machine-12',
        'is_active' => true,
    ]);

    Carbon::setTestNow('2026-05-20 08:18:11');

    $response = $this->actingAs($user)->get(route('locations.qr-pack', $location));

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );

    Carbon::setTestNow();

    $zip = new \ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'wp-docx-test-');
    file_put_contents($tmp, $response->streamedContent());
    expect($zip->open($tmp))->toBeTrue();
    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($tmp);

    expect($documentXml)->toBeString()
        ->and($documentXml)->toContain('Machine 12')
        ->and($documentXml)->toContain('Hal A');
});
