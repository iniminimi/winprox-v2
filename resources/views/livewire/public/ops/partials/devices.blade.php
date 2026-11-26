<x-wp-page-head-title
    variant="portal"
    icon="search"
    :title="__('ops.menu.devices')"
    :subtitle="__('ops.menu.devices_hint')"
/>

<div class="wp-card wp-card-pad wp-stack">
    <label class="wp-label" for="ops-device-search">{{ __('ops.devices.search') }}</label>
    <input
        id="ops-device-search"
        type="search"
        class="wp-input"
        wire:model.live.debounce.300ms="deviceSearch"
        placeholder="{{ __('ops.devices.search_placeholder') }}"
        autocomplete="off"
    >

    @if ($devices->isEmpty())
        <p class="wp-muted">{{ __('ops.devices.empty') }}</p>
    @else
        <ul class="wp-list-plain wp-stack-tight">
            @foreach ($devices as $device)
                <li class="wp-list-row" wire:key="ops-device-{{ $device->id }}">
                    <div class="wp-grow">
                        <p class="wp-text-body">
                            <strong>{{ $device->serial_number }}</strong>
                            <span class="wp-pill">{{ __('ops.device_status.'.$device->status->value) }}</span>
                        </p>
                        <p class="wp-muted wp-text-sm">
                            @if ($device->mac)
                                {{ __('ops.fields.mac') }} {{ \App\Support\Ops\HardwareMac::formatForDisplay($device->mac) }}
                            @else
                                {{ __('ops.devices.mac_missing') }}
                            @endif
                            · {{ $device->brand }} {{ $device->model }}
                            @if ($device->sku)
                                · SKU {{ $device->sku }}
                            @endif
                        </p>
                        @include('livewire.ops.device-placement', ['placement' => $placements[$device->id] ?? null])
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
