<?php

declare(strict_types=1);

use App\Actions\Ops\AssignHardwareDeviceTenantAction;
use App\Actions\Ops\BindHardwareDeviceMacAction;
use App\Actions\Ops\EnsureOpsPortalAction;
use App\Actions\Ops\RegisterHardwareDeviceAction;
use App\Actions\Ops\ResolveHardwareDeviceByMacAction;
use App\Actions\Time\ConfirmClockDisplayClaimAction;
use App\Actions\Time\IssueClockDisplayPairingCodeAction;
use App\Actions\Time\SubmitClockDisplayClaimAction;
use App\Data\Ops\AssignHardwareDeviceTenantData;
use App\Data\Ops\BindHardwareDeviceMacData;
use App\Data\Ops\RegisterHardwareDeviceData;
use App\Enums\HardwareDeviceStatus;
use App\Enums\HardwareDeviceType;
use App\Livewire\Platform\Devices;
use App\Livewire\Public\OpsPortal;
use App\Models\ClockPoint;
use App\Models\HardwareDevice;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatUnansweredQuestion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function opsSuperuser(): User
{
    return User::factory()->superuser()->create();
}

function opsTenantWithPoint(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $point = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    return compact('tenant', 'admin', 'point');
}

it('registreert een toestel met S/N en optionele SKU', function () {
    $super = opsSuperuser();

    $device = app(RegisterHardwareDeviceAction::class)->handle(
        RegisterHardwareDeviceData::fromValidated([
            'serial_number' => '114993068263500199',
            'brand' => 'Seeed',
            'type' => HardwareDeviceType::ClockDisplay->value,
            'model' => 'Indicator D1',
            'sku' => 'D1-CLK-01',
        ]),
        (int) $super->id,
    );

    expect($device->serial_number)->toBe('114993068263500199')
        ->and($device->sku)->toBe('D1-CLK-01')
        ->and($device->status)->toBe(HardwareDeviceStatus::InStock)
        ->and($device->sn_scanned_at)->not->toBeNull()
        ->and($device->events()->count())->toBe(1);
});

it('koppelt MAC eenmalig aan S/N en matcht inventaris bij schermclaim', function () {
    $super = opsSuperuser();
    ['tenant' => $tenant, 'point' => $point] = opsTenantWithPoint();

    $device = app(RegisterHardwareDeviceAction::class)->handle(
        RegisterHardwareDeviceData::fromValidated([
            'serial_number' => 'SN-OPS-001',
            'brand' => 'Seeed',
            'type' => HardwareDeviceType::ClockDisplay->value,
        ]),
        (int) $super->id,
    );

    app(BindHardwareDeviceMacAction::class)->handle(
        $device,
        BindHardwareDeviceMacData::fromValidated(['mac' => 'AA:BB:CC:DD:EE:01']),
        (int) $super->id,
    );

    expect(app(ResolveHardwareDeviceByMacAction::class)->handle('AABBCCDDEE01')?->serial_number)
        ->toBe('SN-OPS-001');

    expect(fn () => app(BindHardwareDeviceMacAction::class)->handle(
        $device->fresh(),
        BindHardwareDeviceMacData::fromValidated(['mac' => 'AA:BB:CC:DD:EE:02']),
        (int) $super->id,
    ))->toThrow(InvalidArgumentException::class, 'mac_already_set');

    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'AABBCCDDEE01', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, (int) $super->id);

    $device->refresh();
    expect($device->tenant_id)->toBe($tenant->id)
        ->and($device->clock_point_id)->toBe($point->id)
        ->and($device->status)->toBe(HardwareDeviceStatus::InUse)
        ->and($device->assigned_to_tenant_at)->not->toBeNull();
});

it('hergebruikt een toestel bij een andere tenant', function () {
    $super = opsSuperuser();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $device = HardwareDevice::factory()->withMac('AABBCCDDEE99')->create([
        'serial_number' => 'SN-REUSE-1',
    ]);

    app(AssignHardwareDeviceTenantAction::class)->handle(
        $device,
        AssignHardwareDeviceTenantData::fromValidated(['tenant_id' => $tenantA->id]),
        (int) $super->id,
    );

    $device->refresh();
    expect($device->tenant_id)->toBe($tenantA->id)
        ->and($device->status)->toBe(HardwareDeviceStatus::Assigned);

    app(AssignHardwareDeviceTenantAction::class)->handle(
        $device->fresh(),
        AssignHardwareDeviceTenantData::fromValidated(['tenant_id' => null]),
        (int) $super->id,
    );

    app(AssignHardwareDeviceTenantAction::class)->handle(
        $device->fresh(),
        AssignHardwareDeviceTenantData::fromValidated(['tenant_id' => $tenantB->id]),
        (int) $super->id,
    );

    $device->refresh();
    expect($device->tenant_id)->toBe($tenantB->id)
        ->and($device->mac)->toBe('AABBCCDDEE99')
        ->and($device->serial_number)->toBe('SN-REUSE-1');
});

it('toont platform toestellen en OPS-portaal voor superuser', function () {
    $super = opsSuperuser();
    HardwareDevice::factory()->create(['serial_number' => 'SN-UI-1']);
    $portal = app(EnsureOpsPortalAction::class)->handle();

    Livewire::actingAs($super)
        ->test(Devices::class)
        ->assertSee('SN-UI-1')
        ->assertSee($portal->portalUrl(), false);

    $this->get(route('public.ops-portal', $portal->token))
        ->assertOk()
        ->assertSee(__('ops.portal.title'), false);

    Livewire::test(OpsPortal::class, ['token' => $portal->token])
        ->set('email', $super->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertSet('screen', 'home')
        ->call('openScreen', 'register')
        ->set('serial_number', 'SN-PHONE-1')
        ->call('register')
        ->assertHasNoErrors();

    expect(HardwareDevice::query()->where('serial_number', 'SN-PHONE-1')->exists())->toBeTrue();
});

it('laat via OPS gsm wachtwoord zetten, toestel zoeken en help beantwoorden', function () {
    $super = opsSuperuser();
    $tenant = Tenant::factory()->create(['name' => 'Acme OPS']);
    $colleague = User::factory()->admin()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Locked User',
        'email' => 'locked-ops@example.com',
    ]);
    HardwareDevice::factory()->withMac('AABBCCDDEE55')->create([
        'serial_number' => 'SN-FIND-55',
        'tenant_id' => $tenant->id,
    ]);
    $question = HelpChatUnansweredQuestion::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $colleague->id,
        'locale' => 'nl',
        'question' => 'Hoe reset ik mijn wachtwoord in OPS test?',
    ]);
    $portal = app(EnsureOpsPortalAction::class)->handle();

    Livewire::test(OpsPortal::class, ['token' => $portal->token])
        ->set('email', $super->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->call('openScreen', 'devices')
        ->set('deviceSearch', 'SN-FIND-55')
        ->assertSee('SN-FIND-55')
        ->assertSee('AA:BB:CC:DD:EE:55', false)
        ->call('openScreen', 'users')
        ->set('userSearch', 'locked-ops@example.com')
        ->call('openPassword', $colleague->id)
        ->set('newPassword', 'nieuw-wachtwoord-123')
        ->set('newPasswordConfirmation', 'nieuw-wachtwoord-123')
        ->call('savePassword')
        ->assertHasNoErrors()
        ->call('openScreen', 'help')
        ->assertSee('Hoe reset ik mijn wachtwoord in OPS test?')
        ->call('openAnswerQuestion', $question->id)
        ->set('kbAnswer', 'Via Platform of OPS kun je een nieuw wachtwoord zetten.')
        ->call('saveAnswer')
        ->assertHasNoErrors();

    expect(Hash::check('nieuw-wachtwoord-123', $colleague->fresh()->password))->toBeTrue()
        ->and(HelpChatUnansweredQuestion::query()->whereKey($question->id)->exists())->toBeFalse()
        ->and(HelpChatKnowledgeBaseEntry::query()->where('match_key', 'like', '%wachtwoord%')->exists())->toBeTrue();
});

it('weigert niet-superusers op platform toestellen', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('platform.devices'))
        ->assertForbidden();
});
