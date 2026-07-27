<?php

declare(strict_types=1);

namespace App\Livewire\Iot;

use App\Actions\Iot\CreateIotGatewayAction;
use App\Actions\Iot\CreateIotRuleAction;
use App\Actions\Iot\CreateIotSensorAction;
use App\Actions\Iot\RotateIotGatewayTokenAction;
use App\Actions\Iot\SetIotGatewayActiveAction;
use App\Actions\Iot\SetIotRuleActiveAction;
use App\Actions\Iot\SetIotSensorActiveAction;
use App\Actions\Iot\UpdateIotGatewayAction;
use App\Actions\Iot\UpdateIotRuleAction;
use App\Actions\Iot\UpdateIotSensorAction;
use App\Enums\IotRuleOperator;
use App\Enums\IotSensorType;
use App\Enums\TaskPriority;
use App\Http\Requests\Iot\StoreIotRuleRequest;
use App\Http\Requests\Iot\StoreIotSensorRequest;
use App\Models\EsgIndicator;
use App\Models\InternalTeam;
use App\Models\IotEvent;
use App\Models\IotGateway;
use App\Models\IotRule;
use App\Models\IotSensor;
use App\Models\Unit;
use App\Support\Esg\EsgModuleAccess;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    use AuthorizesRequests;

    public string $tab = 'gateways';

    public bool $showGatewayModal = false;

    public bool $showSensorModal = false;

    public bool $showRuleModal = false;

    public ?int $editingGatewayId = null;

    public ?int $editingSensorId = null;

    public ?int $editingRuleId = null;

    public string $gatewayName = '';

    public ?string $createdGatewayToken = null;

    public ?int $sensorGatewayId = null;

    public string $sensorExternalId = '';

    public string $sensorName = '';

    public string $sensorType = 'water_leak';

    public ?int $sensorLocationId = null;

    public ?int $sensorUnitId = null;

    public ?int $sensorEsgIndicatorId = null;

    public ?int $ruleSensorId = null;

    public string $ruleName = '';

    public string $ruleOperator = 'gte';

    public string $ruleThreshold = '1';

    public string $ruleDescription = '';

    public ?int $ruleTeamId = null;

    public string $rulePriority = 'prio_2';

    public function mount(): void
    {
        $this->authorize('viewAny', IotGateway::class);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['gateways', 'sensors', 'rules', 'events'], true)) {
            $this->tab = $tab;
        }
    }

    public function openGatewayModal(): void
    {
        $this->authorize('create', IotGateway::class);
        $this->editingGatewayId = null;
        $this->gatewayName = '';
        $this->createdGatewayToken = null;
        $this->showGatewayModal = true;
    }

    public function openEditGatewayModal(int $gatewayId): void
    {
        $gateway = IotGateway::query()->findOrFail($gatewayId);
        $this->authorize('update', $gateway);
        $this->editingGatewayId = (int) $gateway->id;
        $this->gatewayName = (string) $gateway->name;
        $this->createdGatewayToken = null;
        $this->showGatewayModal = true;
    }

    public function closeGatewayModal(): void
    {
        $this->showGatewayModal = false;
        $this->editingGatewayId = null;
        $this->gatewayName = '';
        $this->createdGatewayToken = null;
    }

    public function saveGateway(CreateIotGatewayAction $create, UpdateIotGatewayAction $update): void
    {
        $validated = $this->validate([
            'gatewayName' => ['required', 'string', 'max:120'],
        ]);

        if ($this->editingGatewayId !== null) {
            $gateway = IotGateway::query()->findOrFail($this->editingGatewayId);
            $this->authorize('update', $gateway);
            $update->handle($gateway, (string) $validated['gatewayName'], (int) auth()->id());
            $this->closeGatewayModal();
            session()->flash('success', __('iot.flash.gateway_updated'));

            return;
        }

        $this->authorize('create', IotGateway::class);
        $result = $create->handle(
            (string) $validated['gatewayName'],
            (int) Tenancy::id(),
            (int) auth()->id(),
        );
        $this->createdGatewayToken = $result['plain_token'];
        $this->gatewayName = '';
        session()->flash('success', __('iot.flash.gateway_created'));
    }

    public function rotateGatewayToken(RotateIotGatewayTokenAction $rotate): void
    {
        if ($this->editingGatewayId === null) {
            return;
        }

        $gateway = IotGateway::query()->findOrFail($this->editingGatewayId);
        $this->authorize('update', $gateway);
        $result = $rotate->handle($gateway, (int) auth()->id());
        $this->createdGatewayToken = $result['plain_token'];
        session()->flash('success', __('iot.flash.gateway_token_rotated'));
    }

    public function toggleGateway(int $gatewayId, SetIotGatewayActiveAction $toggle): void
    {
        $gateway = IotGateway::query()->findOrFail($gatewayId);
        $this->authorize('update', $gateway);
        $toggle->handle($gateway, ! $gateway->is_active, (int) auth()->id());
    }

    public function openSensorModal(): void
    {
        $this->authorize('create', IotSensor::class);
        $this->editingSensorId = null;
        $this->resetSensorForm();
        $this->showSensorModal = true;
    }

    public function openEditSensorModal(int $sensorId): void
    {
        $sensor = IotSensor::query()->findOrFail($sensorId);
        $this->authorize('update', $sensor);
        $this->editingSensorId = (int) $sensor->id;
        $this->sensorGatewayId = (int) $sensor->iot_gateway_id;
        $this->sensorExternalId = (string) $sensor->external_id;
        $this->sensorName = (string) $sensor->name;
        $this->sensorType = $sensor->sensor_type->value;
        $this->sensorLocationId = $sensor->location_id !== null ? (int) $sensor->location_id : null;
        $this->sensorUnitId = $sensor->unit_id !== null ? (int) $sensor->unit_id : null;
        $this->sensorEsgIndicatorId = $sensor->esg_indicator_id !== null ? (int) $sensor->esg_indicator_id : null;
        $this->showSensorModal = true;
    }

    public function closeSensorModal(): void
    {
        $this->showSensorModal = false;
        $this->editingSensorId = null;
        $this->resetSensorForm();
    }

    public function saveSensor(CreateIotSensorAction $create, UpdateIotSensorAction $update): void
    {
        $rules = StoreIotSensorRequest::ruleSet();
        $validated = $this->validate([
            'sensorGatewayId' => $rules['iot_gateway_id'],
            'sensorExternalId' => $rules['external_id'],
            'sensorName' => $rules['name'],
            'sensorType' => $rules['sensor_type'],
            'sensorLocationId' => $rules['location_id'],
            'sensorUnitId' => $rules['unit_id'],
            'sensorEsgIndicatorId' => $rules['esg_indicator_id'],
        ]);

        $payload = [
            'iot_gateway_id' => (int) $validated['sensorGatewayId'],
            'external_id' => (string) $validated['sensorExternalId'],
            'name' => (string) $validated['sensorName'],
            'sensor_type' => (string) $validated['sensorType'],
            'location_id' => $validated['sensorLocationId'] ?? null,
            'unit_id' => $validated['sensorUnitId'] ?? null,
            'esg_indicator_id' => $validated['sensorEsgIndicatorId'] ?? null,
        ];

        if ($this->editingSensorId !== null) {
            $sensor = IotSensor::query()->findOrFail($this->editingSensorId);
            $this->authorize('update', $sensor);
            $update->handle($sensor, $payload, (int) Tenancy::id(), (int) auth()->id());
            $this->closeSensorModal();
            session()->flash('success', __('iot.flash.sensor_updated'));

            return;
        }

        $this->authorize('create', IotSensor::class);
        $create->handle($payload, (int) Tenancy::id(), (int) auth()->id());
        $this->closeSensorModal();
        session()->flash('success', __('iot.flash.sensor_created'));
    }

    public function toggleSensor(int $sensorId, SetIotSensorActiveAction $toggle): void
    {
        $sensor = IotSensor::query()->findOrFail($sensorId);
        $this->authorize('update', $sensor);
        $toggle->handle($sensor, ! $sensor->is_active, (int) auth()->id());
    }

    public function openRuleModal(): void
    {
        $this->authorize('create', IotRule::class);
        $this->editingRuleId = null;
        $this->resetRuleForm();
        $this->showRuleModal = true;
    }

    public function openEditRuleModal(int $ruleId): void
    {
        $rule = IotRule::query()->findOrFail($ruleId);
        $this->authorize('update', $rule);
        $this->editingRuleId = (int) $rule->id;
        $this->ruleSensorId = (int) $rule->iot_sensor_id;
        $this->ruleName = (string) $rule->name;
        $this->ruleOperator = $rule->operator->value;
        $this->ruleThreshold = (string) $rule->threshold;
        $this->ruleDescription = (string) $rule->description;
        $this->ruleTeamId = $rule->internal_team_id !== null ? (int) $rule->internal_team_id : null;
        $this->rulePriority = $rule->priority->value;
        $this->showRuleModal = true;
    }

    public function closeRuleModal(): void
    {
        $this->showRuleModal = false;
        $this->editingRuleId = null;
        $this->resetRuleForm();
    }

    public function saveRule(CreateIotRuleAction $create, UpdateIotRuleAction $update): void
    {
        $rules = StoreIotRuleRequest::ruleSet();
        $validated = $this->validate([
            'ruleSensorId' => $rules['iot_sensor_id'],
            'ruleName' => $rules['name'],
            'ruleOperator' => $rules['operator'],
            'ruleThreshold' => $rules['threshold'],
            'ruleDescription' => $rules['description'],
            'ruleTeamId' => $rules['internal_team_id'],
            'rulePriority' => $rules['priority'],
        ]);

        $payload = [
            'iot_sensor_id' => (int) $validated['ruleSensorId'],
            'name' => (string) $validated['ruleName'],
            'operator' => (string) $validated['ruleOperator'],
            'threshold' => $validated['ruleThreshold'],
            'description' => (string) $validated['ruleDescription'],
            'internal_team_id' => $validated['ruleTeamId'] ?? null,
            'priority' => $validated['rulePriority'] ?? TaskPriority::Prio2->value,
        ];

        if ($this->editingRuleId !== null) {
            $rule = IotRule::query()->findOrFail($this->editingRuleId);
            $this->authorize('update', $rule);
            $update->handle($rule, $payload, (int) Tenancy::id(), (int) auth()->id());
            $this->closeRuleModal();
            session()->flash('success', __('iot.flash.rule_updated'));

            return;
        }

        $this->authorize('create', IotRule::class);
        $create->handle($payload, (int) Tenancy::id(), (int) auth()->id());
        $this->closeRuleModal();
        session()->flash('success', __('iot.flash.rule_created'));
    }

    public function toggleRule(int $ruleId, SetIotRuleActiveAction $toggle): void
    {
        $rule = IotRule::query()->findOrFail($ruleId);
        $this->authorize('update', $rule);
        $toggle->handle($rule, ! $rule->is_active, (int) auth()->id());
    }

    public function render()
    {
        $hasEsg = EsgModuleAccess::activeTenantHasModule();

        return view('livewire.iot.index', [
            'gateways' => IotGateway::query()->latest()->limit(50)->get(),
            'sensors' => IotSensor::query()->with(['gateway', 'location', 'unit'])->latest()->limit(100)->get(),
            'rules' => IotRule::query()->with(['sensor', 'team'])->latest()->limit(100)->get(),
            'events' => IotEvent::query()->with(['sensor', 'issue'])->latest('received_at')->limit(50)->get(),
            'units' => Unit::query()->orderBy('name')->get(),
            'teams' => InternalTeam::query()->where('is_active', true)->orderBy('name')->get(),
            'indicators' => $hasEsg
                ? EsgIndicator::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'sensorTypes' => IotSensorType::cases(),
            'operators' => IotRuleOperator::cases(),
            'priorities' => TaskPriority::cases(),
            'hasEsg' => $hasEsg,
        ]);
    }

    private function resetSensorForm(): void
    {
        $this->sensorGatewayId = null;
        $this->sensorExternalId = '';
        $this->sensorName = '';
        $this->sensorType = IotSensorType::WaterLeak->value;
        $this->sensorLocationId = null;
        $this->sensorUnitId = null;
        $this->sensorEsgIndicatorId = null;
    }

    private function resetRuleForm(): void
    {
        $this->ruleSensorId = null;
        $this->ruleName = '';
        $this->ruleOperator = IotRuleOperator::Gte->value;
        $this->ruleThreshold = '1';
        $this->ruleDescription = '';
        $this->ruleTeamId = null;
        $this->rulePriority = TaskPriority::Prio2->value;
    }
}
