<?php

declare(strict_types=1);

use App\Actions\Ops\BindHardwareDeviceMacAction;
use App\Actions\Ops\RegisterHardwareDeviceAction;
use App\Data\Ops\BindHardwareDeviceMacData;
use App\Data\Ops\RegisterHardwareDeviceData;
use App\Enums\HardwareDeviceType;
use App\Livewire\Platform\Devices;
use App\Models\AuditLog;
use App\Models\HardwareDevice;
use App\Models\User;
use App\Support\Ops\UsbClockBridge;
use Livewire\Livewire;

class FakeUsbClockBridge extends UsbClockBridge
{
    public int $flashCalls = 0;

    public int $reads = 0;

    public function __construct(
        public bool $ready = true,
        public array $ports = ['COM3'],
        public ?string $mac = 'AABBCCDDEE01',
        public ?string $macAfter = null,
        public bool $flashed = true,
    ) {}

    public function available(): bool
    {
        return $this->ready;
    }

    public function clockPorts(): array
    {
        return $this->ready ? $this->ports : [];
    }

    public function readMac(string $port): ?string
    {
        $this->reads++;

        if ($this->reads > 1 && $this->macAfter !== null) {
            return $this->macAfter;
        }

        return $this->mac;
    }

    public function flash(string $port): bool
    {
        $this->flashCalls++;

        return $this->flashed;
    }
}

function flashClock(string $serial = 'SN-FLASH-1'): HardwareDevice
{
    $super = opsSuperuser();

    return app(RegisterHardwareDeviceAction::class)->handle(
        RegisterHardwareDeviceData::fromValidated([
            'serial_number' => $serial,
            'brand' => 'Seeed',
            'type' => HardwareDeviceType::ClockDisplay->value,
            'model' => 'Indicator D1',
        ]),
        (int) $super->id,
    );
}

it('vraagt bevestiging en flasht het klokscherm aan usb', function () {
    $super = opsSuperuser();
    $device = flashClock();
    app(BindHardwareDeviceMacAction::class)->handle(
        $device,
        BindHardwareDeviceMacData::fromValidated(['mac' => 'AA:BB:CC:DD:EE:01']),
        (int) $super->id,
    );
    $fake = new FakeUsbClockBridge;
    app()->instance(UsbClockBridge::class, $fake);

    $type = __('ops.device_type.clock_display').' Indicator D1';
    $mac = 'AA:BB:CC:DD:EE:01';

    Livewire::actingAs($super)
        ->test(Devices::class)
        ->assertSee(__('ops.devices.flash'))
        ->call('detectFlash')
        ->assertSet('showFlashConfirm', true)
        ->assertSee(__('ops.devices.flash_confirm', ['type' => $type, 'mac' => $mac]))
        ->call('cancelFlash')
        ->assertSet('showFlashConfirm', false);

    expect($fake->flashCalls)->toBe(0);

    Livewire::actingAs($super)
        ->test(Devices::class)
        ->call('detectFlash')
        ->call('confirmFlash')
        ->assertSet('showFlashConfirm', false)
        ->assertSee(__('ops.devices.flash_ok', ['type' => $type, 'mac' => $mac]));

    expect($fake->flashCalls)->toBe(1)
        ->and($device->fresh()->events()->where('event_type', 'flashed')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'hardware_device.flashed')->count())->toBe(1);
});

it('meldt een mislukte flash', function () {
    $super = opsSuperuser();
    $fake = new FakeUsbClockBridge(flashed: false);
    app()->instance(UsbClockBridge::class, $fake);

    Livewire::actingAs($super)
        ->test(Devices::class)
        ->call('detectFlash')
        ->call('confirmFlash')
        ->assertSee(__('ops.devices.flash_failed', [
            'type' => __('ops.device_type.clock_display'),
            'mac' => 'AA:BB:CC:DD:EE:01',
        ]));

    expect(AuditLog::query()->where('action', 'hardware_device.flash_failed')->count())->toBe(1);
});

it('flasht niet wanneer het toestel aan usb wisselt', function () {
    $super = opsSuperuser();
    $fake = new FakeUsbClockBridge(macAfter: 'AABBCCDDEE02');
    app()->instance(UsbClockBridge::class, $fake);

    Livewire::actingAs($super)
        ->test(Devices::class)
        ->call('detectFlash')
        ->call('confirmFlash')
        ->assertSee(__('ops.devices.flash_changed'));

    expect($fake->flashCalls)->toBe(0);
});

it('meldt wanneer er geen klokscherm aan usb hangt', function () {
    $super = opsSuperuser();
    app()->instance(UsbClockBridge::class, new FakeUsbClockBridge(ports: []));

    Livewire::actingAs($super)
        ->test(Devices::class)
        ->call('detectFlash')
        ->assertSet('showFlashConfirm', false)
        ->assertSee(__('ops.devices.flash_none'));
});

it('flasht via deze computer wanneer de pagina erom vraagt', function () {
    app()->instance(UsbClockBridge::class, new FakeUsbClockBridge);

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->postJson('/platform/usb-flash/detect', [], ['Origin' => 'https://winprox.app'])
        ->assertOk()
        ->assertJsonPath('status', 'found')
        ->assertJsonPath('mac', 'AA:BB:CC:DD:EE:01');

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->postJson('/platform/usb-flash/run', ['mac' => 'AABBCCDDEE01'], ['Origin' => 'https://winprox.app'])
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});

it('weigert usb-flash van een ander adres of een andere site', function () {
    app()->instance(UsbClockBridge::class, new FakeUsbClockBridge);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
        ->postJson('/platform/usb-flash/detect', [], ['Origin' => 'https://winprox.app'])
        ->assertForbidden();

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->postJson('/platform/usb-flash/detect', [], ['Origin' => 'https://example.test'])
        ->assertForbidden();
});

it('laat een medewerker niet flashen', function () {
    $employee = User::factory()->employee()->create();

    expect($employee->can('flash', HardwareDevice::class))->toBeFalse();

    Livewire::actingAs($employee)
        ->test(Devices::class)
        ->assertForbidden();
});
