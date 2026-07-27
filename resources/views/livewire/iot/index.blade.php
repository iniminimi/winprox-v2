<div class="wp-stack" data-manual-capture="iot-connect">
    <x-wp-page-head-title
        icon="api"
        :title="__('iot.title')"
        help-page="iot.index"
        :subtitle="__('iot.subtitle')"
    />

    @if (session('success'))
        <div class="wp-card wp-card-pad">
            <p class="wp-text-body">{{ session('success') }}</p>
        </div>
    @endif

    <nav class="wp-cluster wp-cluster--tight" aria-label="{{ __('iot.nav.aria') }}">
        @foreach (['gateways', 'sensors', 'rules', 'events'] as $tabKey)
            <button type="button"
                    class="btn btn--sm {{ $tab === $tabKey ? 'btn--primary' : 'btn--ghost' }}"
                    wire:click="setTab('{{ $tabKey }}')">
                {{ __('iot.nav.'.$tabKey) }}
            </button>
        @endforeach
    </nav>

    @if ($tab === 'gateways')
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-cluster wp-cluster--between">
                <p class="wp-section-title">{{ __('iot.gateways.title') }}</p>
                @can('create', App\Models\IotGateway::class)
                    <button type="button" class="btn btn--primary btn--sm" wire:click="openGatewayModal">
                        {{ __('iot.gateways.add') }}
                    </button>
                @endcan
            </div>

            @if ($gateways->isEmpty())
                <p class="wp-muted">{{ __('iot.gateways.empty') }}</p>
            @else
                <ul class="wp-list-plain wp-stack-tight">
                    @foreach ($gateways as $gateway)
                        <li class="wp-list-row" wire:key="iot-gateway-{{ $gateway->id }}">
                            <div>
                                <strong>{{ $gateway->name }}</strong>
                                <p class="wp-muted wp-text-sm">
                                    {{ __('iot.gateways.token_prefix', ['prefix' => $gateway->token_prefix]) }}
                                    @if ($gateway->last_seen_at)
                                        · {{ __('iot.gateways.last_seen', ['at' => $gateway->last_seen_at->format('d-m-Y H:i')]) }}
                                    @endif
                                </p>
                            </div>
                            <div class="wp-cluster">
                                <span class="wp-pill {{ $gateway->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                                    {{ $gateway->is_active ? __('iot.status.active') : __('iot.status.inactive') }}
                                </span>
                                @can('update', $gateway)
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditGatewayModal({{ $gateway->id }})">
                                        {{ __('common.button.edit') }}
                                    </button>
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleGateway({{ $gateway->id }})">
                                        {{ $gateway->is_active ? __('iot.actions.deactivate') : __('iot.actions.activate') }}
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($tab === 'sensors')
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-cluster wp-cluster--between">
                <p class="wp-section-title">{{ __('iot.sensors.title') }}</p>
                @can('create', App\Models\IotSensor::class)
                    <button type="button" class="btn btn--primary btn--sm" wire:click="openSensorModal">
                        {{ __('iot.sensors.add') }}
                    </button>
                @endcan
            </div>

            @if ($sensors->isEmpty())
                <p class="wp-muted">{{ __('iot.sensors.empty') }}</p>
            @else
                <ul class="wp-list-plain wp-stack-tight">
                    @foreach ($sensors as $sensor)
                        <li class="wp-list-row" wire:key="iot-sensor-{{ $sensor->id }}">
                            <div>
                                <strong>{{ $sensor->name }}</strong>
                                <p class="wp-muted wp-text-sm">
                                    {{ $sensor->external_id }}
                                    · {{ __('iot.sensor_types.'.$sensor->sensor_type->value) }}
                                    · {{ $sensor->gateway?->name }}
                                </p>
                            </div>
                            <div class="wp-cluster">
                                <span class="wp-pill {{ $sensor->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                                    {{ $sensor->is_active ? __('iot.status.active') : __('iot.status.inactive') }}
                                </span>
                                @can('update', $sensor)
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditSensorModal({{ $sensor->id }})">
                                        {{ __('common.button.edit') }}
                                    </button>
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleSensor({{ $sensor->id }})">
                                        {{ $sensor->is_active ? __('iot.actions.deactivate') : __('iot.actions.activate') }}
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($tab === 'rules')
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-cluster wp-cluster--between">
                <p class="wp-section-title">{{ __('iot.rules.title') }}</p>
                @can('create', App\Models\IotRule::class)
                    <button type="button" class="btn btn--primary btn--sm" wire:click="openRuleModal">
                        {{ __('iot.rules.add') }}
                    </button>
                @endcan
            </div>

            @if ($rules->isEmpty())
                <p class="wp-muted">{{ __('iot.rules.empty') }}</p>
            @else
                <ul class="wp-list-plain wp-stack-tight">
                    @foreach ($rules as $rule)
                        <li class="wp-list-row" wire:key="iot-rule-{{ $rule->id }}">
                            <div>
                                <strong>{{ $rule->name }}</strong>
                                <p class="wp-muted wp-text-sm">
                                    {{ $rule->sensor?->name }}
                                    · {{ __('iot.operators.'.$rule->operator->value) }} {{ $rule->threshold }}
                                </p>
                            </div>
                            <div class="wp-cluster">
                                <span class="wp-pill {{ $rule->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                                    {{ $rule->is_active ? __('iot.status.active') : __('iot.status.inactive') }}
                                </span>
                                @can('update', $rule)
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditRuleModal({{ $rule->id }})">
                                        {{ __('common.button.edit') }}
                                    </button>
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleRule({{ $rule->id }})">
                                        {{ $rule->is_active ? __('iot.actions.deactivate') : __('iot.actions.activate') }}
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($tab === 'events')
        <div class="wp-card wp-card-pad wp-stack-tight">
            <p class="wp-section-title">{{ __('iot.events.title') }}</p>
            @if ($events->isEmpty())
                <p class="wp-muted">{{ __('iot.events.empty') }}</p>
            @else
                <ul class="wp-list-plain wp-stack-tight">
                    @foreach ($events as $event)
                        <li class="wp-list-row" wire:key="iot-event-{{ $event->id }}">
                            <div>
                                <strong>{{ $event->external_sensor_id }}</strong>
                                <p class="wp-muted wp-text-sm">
                                    {{ __('iot.kinds.'.$event->kind->value) }}
                                    · {{ __('iot.event_status.'.$event->status->value) }}
                                    · {{ $event->received_at?->format('d-m-Y H:i') }}
                                    @if ($event->issue_id)
                                        · #{{ $event->issue_id }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($showGatewayModal)
        <x-wp-modal closeMethod="closeGatewayModal">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingGatewayId ? __('iot.gateways.edit_title') : __('iot.gateways.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeGatewayModal" />
                </div>

                @if ($createdGatewayToken)
                    <p class="wp-text-body">{{ __('iot.gateways.token_once') }}</p>
                    <code class="wp-input">{{ $createdGatewayToken }}</code>
                    <div class="wp-cluster">
                        <button type="button" class="btn btn--primary" wire:click="closeGatewayModal">
                            {{ __('common.button.close') }}
                        </button>
                    </div>
                @else
                    <label class="wp-label" for="iot-gateway-name">{{ __('iot.fields.name') }}</label>
                    <input id="iot-gateway-name" type="text" class="wp-input" wire:model="gatewayName" autocomplete="off">
                    @error('gatewayName') <p class="wp-muted">{{ $message }}</p> @enderror

                    <div class="wp-cluster">
                        <button type="button" class="btn btn--primary" wire:click="saveGateway">{{ __('common.button.save') }}</button>
                        @if ($editingGatewayId)
                            <button type="button" class="btn btn--ghost" wire:click="rotateGatewayToken">{{ __('iot.gateways.rotate_token') }}</button>
                        @endif
                        <button type="button" class="btn btn--ghost" wire:click="closeGatewayModal">{{ __('common.button.cancel') }}</button>
                    </div>
                @endif
            </div>
        </x-wp-modal>
    @endif

    @if ($showSensorModal)
        <x-wp-modal closeMethod="closeSensorModal">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingSensorId ? __('iot.sensors.edit_title') : __('iot.sensors.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeSensorModal" />
                </div>

                <label class="wp-label" for="iot-sensor-gateway">{{ __('iot.fields.gateway') }}</label>
                <select id="iot-sensor-gateway" class="wp-input" wire:model="sensorGatewayId">
                    <option value="">{{ __('iot.fields.choose') }}</option>
                    @foreach ($gateways as $gateway)
                        <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                    @endforeach
                </select>

                <label class="wp-label" for="iot-sensor-external">{{ __('iot.fields.external_id') }}</label>
                <input id="iot-sensor-external" type="text" class="wp-input" wire:model="sensorExternalId" autocomplete="off">

                <label class="wp-label" for="iot-sensor-name">{{ __('iot.fields.name') }}</label>
                <input id="iot-sensor-name" type="text" class="wp-input" wire:model="sensorName" autocomplete="off">

                <label class="wp-label" for="iot-sensor-type">{{ __('iot.fields.sensor_type') }}</label>
                <select id="iot-sensor-type" class="wp-input" wire:model="sensorType">
                    @foreach ($sensorTypes as $type)
                        <option value="{{ $type->value }}">{{ __('iot.sensor_types.'.$type->value) }}</option>
                    @endforeach
                </select>

                <label class="wp-label" for="iot-sensor-unit">{{ __('iot.fields.unit') }}</label>
                <select id="iot-sensor-unit" class="wp-input" wire:model="sensorUnitId">
                    <option value="">{{ __('iot.fields.choose') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->localizedName() }}</option>
                    @endforeach
                </select>

                @if ($hasEsg)
                    <label class="wp-label" for="iot-sensor-esg">{{ __('iot.fields.esg_indicator') }}</label>
                    <select id="iot-sensor-esg" class="wp-input" wire:model="sensorEsgIndicatorId">
                        <option value="">{{ __('iot.fields.choose') }}</option>
                        @foreach ($indicators as $indicator)
                            <option value="{{ $indicator->id }}">{{ $indicator->localizedName() }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="wp-cluster">
                    <button type="button" class="btn btn--primary" wire:click="saveSensor">{{ __('common.button.save') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="closeSensorModal">{{ __('common.button.cancel') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showRuleModal)
        <x-wp-modal closeMethod="closeRuleModal">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingRuleId ? __('iot.rules.edit_title') : __('iot.rules.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeRuleModal" />
                </div>

                <label class="wp-label" for="iot-rule-sensor">{{ __('iot.fields.sensor') }}</label>
                <select id="iot-rule-sensor" class="wp-input" wire:model="ruleSensorId">
                    <option value="">{{ __('iot.fields.choose') }}</option>
                    @foreach ($sensors as $sensor)
                        <option value="{{ $sensor->id }}">{{ $sensor->name }}</option>
                    @endforeach
                </select>

                <label class="wp-label" for="iot-rule-name">{{ __('iot.fields.name') }}</label>
                <input id="iot-rule-name" type="text" class="wp-input" wire:model="ruleName" autocomplete="off">

                <label class="wp-label" for="iot-rule-operator">{{ __('iot.fields.operator') }}</label>
                <select id="iot-rule-operator" class="wp-input" wire:model="ruleOperator">
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->value }}">{{ __('iot.operators.'.$operator->value) }}</option>
                    @endforeach
                </select>

                <label class="wp-label" for="iot-rule-threshold">{{ __('iot.fields.threshold') }}</label>
                <input id="iot-rule-threshold" type="number" step="any" class="wp-input" wire:model="ruleThreshold">

                <label class="wp-label" for="iot-rule-description">{{ __('iot.fields.description') }}</label>
                <textarea id="iot-rule-description" class="wp-input" rows="3" wire:model="ruleDescription"></textarea>

                <label class="wp-label" for="iot-rule-team">{{ __('iot.fields.team') }}</label>
                <select id="iot-rule-team" class="wp-input" wire:model="ruleTeamId">
                    <option value="">{{ __('iot.fields.choose') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                    @endforeach
                </select>

                <label class="wp-label" for="iot-rule-priority">{{ __('iot.fields.priority') }}</label>
                <select id="iot-rule-priority" class="wp-input" wire:model="rulePriority">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>

                <div class="wp-cluster">
                    <button type="button" class="btn btn--primary" wire:click="saveRule">{{ __('common.button.save') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="closeRuleModal">{{ __('common.button.cancel') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
