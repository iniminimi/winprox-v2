<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Actions\Ops\AuthenticateOpsSuperuserAction;
use App\Actions\Ops\BindHardwareDeviceMacAction;
use App\Actions\Ops\RegisterHardwareDeviceAction;
use App\Data\Ops\BindHardwareDeviceMacData;
use App\Data\Ops\RegisterHardwareDeviceData;
use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceType;
use App\Http\Requests\Ops\BindHardwareDeviceMacRequest;
use App\Http\Requests\Ops\OpsPortalLoginRequest;
use App\Http\Requests\Ops\RegisterHardwareDeviceRequest;
use App\Models\HardwareDevice;
use App\Models\OpsPortal as OpsPortalModel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('WinProx OPS')]
class OpsPortal extends Component
{
    use AuthorizesRequests;

    public string $token;

    public OpsPortalModel $portal;

    public string $email = '';

    public string $password = '';

    public string $mode = 'register';

    public string $serial_number = '';

    public string $brand = 'Seeed';

    public string $registerType = 'clock_display';

    public string $model = 'Indicator D1';

    public string $sku = '';

    public string $condition = 'new';

    public string $purchase_ref = '';

    public string $notes = '';

    public string $macSerial = '';

    public string $mac = '';

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->portal = OpsPortalModel::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function login(AuthenticateOpsSuperuserAction $action): void
    {
        $this->validate(OpsPortalLoginRequest::ruleSet());

        try {
            $action->handle(
                $this->email,
                $this->password,
                (string) request()->ip(),
            );
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            if (str_starts_with($message, 'throttle:')) {
                $seconds = (int) substr($message, strlen('throttle:'));
                $this->addError('email', __('auth.errors.throttle', ['seconds' => $seconds]));

                return;
            }

            $this->addError('email', __('ops.errors.'.($message === 'not_superuser' ? 'not_superuser' : 'auth_failed')));

            return;
        }

        $this->password = '';
        session()->regenerate();
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->email = '';
        $this->password = '';
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['register', 'mac'], true)) {
            return;
        }
        $this->mode = $mode;
        $this->resetErrorBag();
        $this->flashMessage = null;
    }

    public function onBarcodeScanned(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        if ($this->mode === 'mac') {
            $this->macSerial = $value;
        } else {
            $this->serial_number = $value;
        }
    }

    public function register(RegisterHardwareDeviceAction $action): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }
        $this->authorize('use', $this->portal);
        $this->authorize('create', HardwareDevice::class);

        $rules = RegisterHardwareDeviceRequest::ruleSet();
        $this->validate([
            'serial_number' => $rules['serial_number'],
            'brand' => $rules['brand'],
            'registerType' => $rules['type'],
            'model' => $rules['model'],
            'sku' => $rules['sku'],
            'condition' => $rules['condition'],
            'purchase_ref' => $rules['purchase_ref'],
            'notes' => $rules['notes'],
        ]);

        try {
            $device = $action->handle(
                RegisterHardwareDeviceData::fromValidated([
                    'serial_number' => $this->serial_number,
                    'brand' => $this->brand,
                    'type' => $this->registerType,
                    'model' => $this->model,
                    'sku' => $this->sku,
                    'condition' => $this->condition,
                    'purchase_ref' => $this->purchase_ref,
                    'notes' => $this->notes,
                ]),
                (int) $user->id,
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('serial_number', __('ops.errors.'.$e->getMessage()));

            return;
        }

        $this->flashMessage = __('ops.flash.registered', ['serial' => $device->serial_number]);
        $this->flashType = 'success';
        $this->serial_number = '';
        $this->sku = '';
        $this->purchase_ref = '';
        $this->notes = '';
        $this->mode = 'mac';
        $this->macSerial = $device->serial_number;
    }

    public function bindMac(BindHardwareDeviceMacAction $action): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }
        $this->authorize('use', $this->portal);
        $this->authorize('create', HardwareDevice::class);

        $this->validate([
            'macSerial' => ['required', 'string', 'max:64'],
            'mac' => BindHardwareDeviceMacRequest::ruleSet()['mac'],
        ]);

        $device = HardwareDevice::query()
            ->where('serial_number', trim($this->macSerial))
            ->first();

        if ($device === null) {
            $this->addError('macSerial', __('ops.errors.device_not_found'));

            return;
        }

        $this->authorize('update', $device);

        try {
            $action->handle(
                $device,
                BindHardwareDeviceMacData::fromValidated(['mac' => $this->mac]),
                (int) $user->id,
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('mac', __('ops.errors.'.$e->getMessage()));

            return;
        }

        $this->flashMessage = __('ops.flash.mac_linked', [
            'serial' => $device->serial_number,
            'mac' => HardwareDevice::query()->whereKey($device->id)->value('mac'),
        ]);
        $this->flashType = 'success';
        $this->mac = '';
        $this->macSerial = '';
    }

    public function render()
    {
        $user = Auth::user();
        $authed = $user !== null && $user->is_superuser;

        return view('livewire.public.ops-portal', [
            'authed' => $authed,
            'types' => HardwareDeviceType::cases(),
            'conditions' => HardwareDeviceCondition::cases(),
        ]);
    }
}
