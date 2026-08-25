<?php

use App\Actions\QrCodes\BatchGenerateQrCodesAction;
use App\Actions\QrCodes\LinkQrCodeToUnitAction;
use App\Enums\QrCodeStatus;
use App\Livewire\Platform\QrConnect;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{tenant: Tenant, location: Location, unit: Unit}
 */
function qrCodeScaffold(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => true,
    ]);

    return compact('tenant', 'location', 'unit');
}

it('generates unique token and sticker number for QR codes', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $qr1 = QrCode::factory()->create(['tenant_id' => $tenant->id]);
    $qr2 = QrCode::factory()->create(['tenant_id' => $tenant->id]);

    expect($qr1->token)->not->toBe($qr2->token)
        ->and($qr1->sticker_number)->not->toBe($qr2->sticker_number)
        ->and($qr1->uuid)->not->toBe($qr2->uuid);
});

it('assigns sequential yyMM canonical sticker numbers with Winprox display label', function () {
    ['tenant' => $tenant] = qrCodeScaffold();
    $prefix = date('ym');

    $qr1 = QrCode::factory()->create(['tenant_id' => $tenant->id]);
    $qr2 = QrCode::factory()->create(['tenant_id' => $tenant->id]);

    expect($qr1->sticker_number)->toMatch('/^'.$prefix.'-\d{5}$/')
        ->and($qr2->sticker_number)->toMatch('/^'.$prefix.'-\d{5}$/')
        ->and((int) substr($qr2->sticker_number, -5))->toBe((int) substr($qr1->sticker_number, -5) + 1)
        ->and($qr1->display_sticker_number)->toBe('Winprox-'.$qr1->sticker_number);
});

it('can link an unassigned QR code to a unit', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $tenant->id]);

    app(LinkQrCodeToUnitAction::class)->handle(
        $qrCode,
        $unit,
        $tenant->id,
        null
    );

    expect($qrCode->fresh()->unit_id)->toBe($unit->id)
        ->and($qrCode->fresh()->status)->toBe(QrCodeStatus::Active)
        ->and($qrCode->fresh()->linked_at)->not->toBeNull();
});

it('prevents linking a QR code that is already linked', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $qrCode = QrCode::factory()->forUnit($unit)->create(['tenant_id' => $tenant->id]);

    expect(fn () => app(LinkQrCodeToUnitAction::class)->handle(
        $qrCode,
        $unit,
        $tenant->id,
        null
    ))->toThrow(\InvalidArgumentException::class);
});

it('prevents linking a damaged QR code', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $qrCode = QrCode::factory()->damaged()->create(['tenant_id' => $tenant->id]);

    expect(fn () => app(LinkQrCodeToUnitAction::class)->handle(
        $qrCode,
        $unit,
        $tenant->id,
        null
    ))->toThrow(\InvalidArgumentException::class);
});

it('prevents linking QR code to unit from different tenant', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $otherTenant = Tenant::factory()->create();
    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $otherTenant->id]);

    expect(fn () => app(LinkQrCodeToUnitAction::class)->handle(
        $qrCode,
        $unit,
        $tenant->id,
        null
    ))->toThrow(\InvalidArgumentException::class);
});

it('can batch generate QR codes', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $qrCodes = app(BatchGenerateQrCodesAction::class)->handle(5, $tenant->id, null);

    expect($qrCodes)->toHaveCount(5)
        ->and($qrCodes->every(fn ($qr) => $qr->tenant_id === $tenant->id))->toBeTrue()
        ->and($qrCodes->every(fn ($qr) => $qr->status === QrCodeStatus::Unassigned))->toBeTrue()
        ->and($qrCodes->every(fn ($qr) => preg_match('/^\d{4}-\d{5}$/', (string) $qr->sticker_number) === 1))->toBeTrue();
});

it('creates fresh QR codes on each batch download instead of reusing recent unassigned codes', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $firstBatch = app(BatchGenerateQrCodesAction::class)->handle(3, $tenant->id, null);
    $secondBatch = app(BatchGenerateQrCodesAction::class)->handle(3, $tenant->id, null);

    $firstIds = $firstBatch->pluck('id')->all();
    $secondIds = $secondBatch->pluck('id')->all();

    expect($secondIds)->not->toEqual($firstIds)
        ->and(count(array_intersect($firstIds, $secondIds)))->toBe(0);
});

it('redirects active QR code scan to unit portal', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $qrCode = QrCode::factory()->forUnit($unit)->create(['tenant_id' => $tenant->id]);

    $response = $this->get("/q/{$qrCode->token}");

    $response->assertRedirect(route('public.unit-portal', ['token' => $unit->qr_token]));
});

it('shows friendly page for unassigned QR code to guest', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $tenant->id]);

    $response = $this->get("/q/{$qrCode->token}");

    $response->assertRedirect(route('public.unassigned-qr-portal', ['token' => $qrCode->token]));
});

it('redirects authenticated user with permission to unassigned portal', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get("/q/{$qrCode->token}");

    $response->assertRedirect(route('public.unassigned-qr-portal', ['token' => $qrCode->token]));
});

it('shows damaged QR code as the invalid scan card', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $qrCode = QrCode::factory()->damaged()->create(['tenant_id' => $tenant->id]);

    $response = $this->get("/q/{$qrCode->token}");

    $response->assertNotFound()
        ->assertSee(__('qr.invalid.title'))
        ->assertSee(__('qr.invalid.welcome'))
        ->assertDontSee(__('error.404.title'));
});

it('shows inactive QR code as the invalid scan card', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $qrCode = QrCode::factory()->inactive()->create(['tenant_id' => $tenant->id]);

    $response = $this->get("/q/{$qrCode->token}");

    $response->assertNotFound()
        ->assertSee(__('qr.invalid.title'))
        ->assertSee(route('welcome'), false)
        ->assertDontSee(__('error.404.title'));
});

it('shows the invalid scan card for a non-existent QR token', function () {
    $this->get('/q/non-existent-token')
        ->assertNotFound()
        ->assertSee(__('qr.invalid.title'))
        ->assertSee(__('qr.invalid.welcome'))
        ->assertDontSee(__('error.404.title'))
        ->assertDontSee(__('error.action.home'));
});

it('shows the invalid scan card for an unknown unassigned-qr URL', function () {
    $this->get('/melden/onbekend/bestaat-niet')
        ->assertNotFound()
        ->assertSee(__('qr.invalid.title'))
        ->assertDontSee(__('error.404.title'));
});

it('logs QR scans', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $qrCode = QrCode::factory()->forUnit($unit)->create(['tenant_id' => $tenant->id]);

    $this->get("/q/{$qrCode->token}");

    expect(QrScan::count())->toBe(1)
        ->and(QrScan::first()->qr_code_id)->toBe($qrCode->id)
        ->and(QrScan::first()->tenant_id)->toBe($tenant->id);
});

it('updates last_scanned_at on QR code', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $qrCode = QrCode::factory()->forUnit($unit)->create([
        'tenant_id' => $tenant->id,
        'last_scanned_at' => null,
    ]);

    $this->get("/q/{$qrCode->token}");

    expect($qrCode->fresh()->last_scanned_at)->not->toBeNull();
});

it('allows linking QR code via Livewire component', function () {
    ['tenant' => $tenant, 'unit' => $unit] = qrCodeScaffold();

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(QrConnect::class, ['token' => $qrCode->token])
        ->set('selectedUnitId', $unit->id)
        ->call('link')
        ->assertHasNoErrors()
        ->assertSet('showSuccess', true);

    expect($qrCode->fresh()->unit_id)->toBe($unit->id);
});

it('prevents linking to unit from different tenant via Livewire', function () {
    ['tenant' => $tenant] = qrCodeScaffold();

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $tenant->id]);

    $otherTenant = Tenant::factory()->create();
    $otherUnit = Unit::factory()->create(['tenant_id' => $otherTenant->id]);

    Livewire::actingAs($user)
        ->test(QrConnect::class, ['token' => $qrCode->token])
        ->set('selectedUnitId', $otherUnit->id)
        ->call('link')
        ->assertHasErrors('selectedUnitId');
});

it('shows units searchable in QrConnect', function () {
    ['tenant' => $tenant, 'location' => $location] = qrCodeScaffold();

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $tenant->id]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Test Unit',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(QrConnect::class, ['token' => $qrCode->token])
        ->assertSee('Test Unit');
});

it('filters units by search in QrConnect', function () {
    ['tenant' => $tenant, 'location' => $location] = qrCodeScaffold();

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $qrCode = QrCode::factory()->unassigned()->create(['tenant_id' => $tenant->id]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kitchen Unit',
        'is_active' => true,
    ]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Bathroom Unit',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(QrConnect::class, ['token' => $qrCode->token])
        ->set('search', 'Kitchen')
        ->assertSee('Kitchen Unit')
        ->assertDontSee('Bathroom Unit');
});
