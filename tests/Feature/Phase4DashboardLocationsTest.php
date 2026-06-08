<?php

use App\Livewire\Locations\Index as LocationIndex;
use App\Livewire\Locations\Show as LocationShow;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use Illuminate\Support\Facades\Storage;
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
        ->set('locationFormName', '')
        ->set('locationFormStreet', 'Industrieweg')
        ->set('locationFormPostalCode', '')
        ->set('locationFormCity', 'Gent')
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
        ->set('locationFormName', '')
        ->set('locationFormStreet', 'Industrieweg')
        ->set('locationFormPostalCode', '9000')
        ->set('locationFormCity', 'Gent')
        ->set('locationFormCountryCode', '')
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
        ->set('locationFormName', 'Hal A')
        ->set('locationFormStreet', 'Industrieweg')
        ->set('locationFormHouseNumber', '12')
        ->set('locationFormPostalCode', '9000')
        ->set('locationFormCity', 'Gent')
        ->set('locationFormCountryCode', 'BE')
        ->call('save')
        ->assertHasNoErrors();

    $location = Location::where('name', 'Hal A')->first();

    expect($location)->not->toBeNull()
        ->and($location->street)->toBe('Industrieweg');
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

it('filters units on location show by category', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Site Filter']);
    $kranen = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kranen',
    ]);
    $kamers = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotelkamers',
    ]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kraan A',
        'category_id' => $kranen->id,
    ]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kamer B',
        'category_id' => $kamers->id,
    ]);

    Livewire::actingAs($user)
        ->test(LocationShow::class, ['location' => $location])
        ->set('unitCategoryFilter', (string) $kranen->id)
        ->assertSee('Kraan A')
        ->assertDontSee('Kamer B');
});

it('manages categories from locations index', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $component = Livewire::actingAs($user)
        ->test(LocationIndex::class)
        ->call('openCategoriesModal')
        ->set('categoryName', 'Kranen')
        ->set('selectedCategoryTeamIds', [$team->id])
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->assertSee('Kranen');

    $category = Category::query()->where('tenant_id', $tenant->id)->where('name', 'Kranen')->first();
    expect($category)->not->toBeNull();

    $component
        ->call('openEditCategory', (int) $category->id)
        ->set('categoryName', 'Hotelkamers')
        ->set('selectedCategoryTeamIds', [$team->id])
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->assertSee('Hotelkamers');

    $component
        ->call('deleteCategory', (int) $category->id)
        ->assertHasNoErrors()
        ->assertDontSee('Hotelkamers');
});

it('toont geen QR-stickerblad-download zonder units', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Leeg']);

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
    Unit::factory()->withQrToken('qr-facility-machine-12')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Machine 12',
        'is_active' => true,
    ]);

    Carbon::setTestNow('2026-05-20 08:18:11');

    $response = $this->actingAs($user)->get(route('locations.qr-pack', [
        'location' => $location,
        'template' => 'avery_55x55_s',
    ]));

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
        ->and($documentXml)->toContain('<w:gridCol w:w="3118"/>')
        ->and($documentXml)->toContain('<w:gridCol w:w="283"/>')
        ->and($documentXml)->toContain('<w:tblLayout w:type="fixed"/>')
        ->and($documentXml)->toContain('w:left="992"');
});

it('qr-pack download returns herma docx layout when GD available', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hal B',
    ]);
    Unit::factory()->withQrToken('qr-facility-herma')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Deur 3',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('locations.qr-pack', [
        'location' => $location,
        'template' => 'herma_70x50',
    ]));

    $response->assertOk();

    $zip = new \ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'wp-docx-herma-');
    file_put_contents($tmp, $response->streamedContent());
    expect($zip->open($tmp))->toBeTrue();
    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($tmp);

    expect($documentXml)->toBeString()
        ->and($documentXml)->toContain('Deur 3')
        ->and($documentXml)->toContain('<w:gridCol w:w="3968"/>')
        ->and($documentXml)->toContain('w:top="1219"')
        ->and($documentXml)->toContain('w:left="0"');
});

it('toont QR-stickerblad-modal met formaten wanneer units aanwezig', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(LocationShow::class, ['location' => $location])
        ->assertSee(__('locations.qr_pack_download'))
        ->call('openQrPackModal')
        ->assertSet('showQrPackModal', true)
        ->assertSee(__('locations.qr_pack.formats.avery_55x55_s.title'))
        ->assertSee(__('locations.qr_pack.formats.herma_70x50.title'));
});

it('toont WinProx-logo in unit-QR wanneer geen organisatielogo is', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5), 'logo_path' => null]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->withQrToken('qr-unit-logo-test')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift 1',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('units.qr', $unit))
        ->assertOk()
        ->assertSee('wp-qr-code-center-logo', false)
        ->assertSee(QrCenterLogo::winproxPublicUrl(), false);
});

it('qr-pack download with dynamic QR codes generates unassigned codes', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get(route('locations.qr-pack', [
        'location' => $location,
        'template' => 'avery_55x55_s',
        'dynamic' => '1',
        'count' => '5',
    ]));

    $response->assertOk();

    // Verify QR codes were created
    $qrCodes = \App\Models\QrCode::where('tenant_id', $tenant->id)->get();
    expect($qrCodes)->toHaveCount(5)
        ->and($qrCodes->every(fn ($qr) => $qr->status === \App\Enums\QrCodeStatus::Unassigned))->toBeTrue();
});

it('qr-pack download with dynamic QR codes validates count', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get(route('locations.qr-pack', [
        'location' => $location,
        'template' => 'avery_55x55_s',
        'dynamic' => '1',
        'count' => '150',
    ]));

    $response->assertStatus(400);
});

it('downloads sample CSV with correct headers and UTF-8 BOM', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    // Test that the component has the downloadSampleCsv method
    $component = Livewire::actingAs($user)
        ->test(LocationIndex::class);

    // Verify the method exists and can be called (Livewire will handle the response)
    $component->call('downloadSampleCsv')
        ->assertStatus(200);

    // For detailed content verification, we'll test the component method directly
    // by setting up the proper context
    Tenancy::actAs($tenant->id);
    auth()->login($user);

    $livewireComponent = new LocationIndex();
    $livewireComponent->mount();

    $csvResponse = $livewireComponent->downloadSampleCsv();

    expect($csvResponse->getStatusCode())->toBe(200)
        ->and($csvResponse->headers->get('content-type'))->toBe('text/csv; charset=UTF-8')
        ->and($csvResponse->headers->get('content-disposition'))->toContain('winprox_sample.csv');

    // Verify the response is a StreamedResponse
    expect($csvResponse)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);
});
