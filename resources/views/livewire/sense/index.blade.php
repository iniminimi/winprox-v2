<div class="wp-stack">
    <div class="wp-page-head">
        <x-wp-page-head-title
            icon="locations"
            :title="__('sense.title')"
            help-page="sense.index"
            :subtitle="__('sense.subtitle')"
        />
        <div class="wp-cluster">
            @can('create', App\Models\SensePoint::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreate">
                    {{ __('sense.add') }}
                </button>
            @endcan
        </div>
    </div>

    @if ($bandError)
        <p class="wp-flash wp-flash--danger">{{ $bandError }}</p>
    @endif

    @if ($rooms === [])
        <div class="wp-card wp-card-pad">
            <p class="wp-text-body">{{ __('sense.empty') }}</p>
        </div>
    @else
        <div class="wp-sense-grid">
            @foreach ($rooms as $room)
                <article class="wp-sense-room" wire:key="sense-room-{{ $room->id }}-{{ $room->temperature->low }}-{{ $room->temperature->target }}-{{ $room->temperature->high }}-{{ $room->humidity->low }}-{{ $room->humidity->target }}-{{ $room->humidity->high }}-{{ $room->co2->high }}-{{ $room->voc->high }}-{{ $room->temperature->value }}-{{ $room->humidity->value }}-{{ $room->co2->value }}-{{ $room->voc->value }}">
                    <div class="wp-sense-card" style="--sense-bg: {{ \App\Support\Sense\SensePalette::BLACK }}; --sense-text: {{ \App\Support\Sense\SensePalette::TEXT }}; --sense-muted: {{ \App\Support\Sense\SensePalette::MUTED }}; --sense-green: {{ \App\Support\Sense\SensePalette::GREEN }}; --sense-red: {{ \App\Support\Sense\SensePalette::RED }};">
                        <header class="wp-sense-card__head">
                            <span class="wp-sense-leaf" aria-hidden="true">
                                <svg viewBox="0 0 24 24" class="wp-sense-svg"><path fill="currentColor" d="M5 19c8 1 13-4 14-14-8 0-14 6-14 14Z"/><path fill="none" stroke="#000" stroke-width="1.6" d="M9 15c2-2 4-5 6-8"/></svg>
                            </span>
                            <h2 class="wp-sense-card__title">{{ $room->name }}</h2>
                            <span @class(['wp-sense-state', 'wp-sense-state--bad' => ! $room->ok]) aria-hidden="true">
                                @if ($room->ok)
                                    <svg viewBox="0 0 24 24" class="wp-sense-svg"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" class="wp-sense-svg"><path fill="currentColor" d="M11 6h2v8h-2V6Zm0 10h2v2h-2v-2Z"/></svg>
                                @endif
                            </span>
                        </header>

                        @include('partials.wp-sense-scale', ['scale' => $room->temperature, 'pointId' => $room->id])
                        @include('partials.wp-sense-scale', ['scale' => $room->humidity, 'pointId' => $room->id])
                        @include('partials.wp-sense-scale', ['scale' => $room->co2, 'pointId' => $room->id])
                        @include('partials.wp-sense-scale', ['scale' => $room->voc, 'pointId' => $room->id])

                        <p class="wp-sense-card__foot">
                            <x-wp-icon name="clock" class="wp-icon" />
                            <span>{{ $room->lastLabel }}</span>
                        </p>
                    </div>

                    @if ($canManage)
                        <div class="wp-cluster wp-sense-tools">
                            <button type="button" @class(['btn', 'btn--sm', 'btn--primary' => $room->preset === 'winter', 'btn--ghost' => $room->preset !== 'winter']) wire:click="applyPreset({{ $room->id }}, 'winter')">
                                {{ __('sense.presets.winter') }}
                            </button>
                            <button type="button" @class(['btn', 'btn--sm', 'btn--primary' => $room->preset === 'summer', 'btn--ghost' => $room->preset !== 'summer']) wire:click="applyPreset({{ $room->id }}, 'summer')">
                                {{ __('sense.presets.summer') }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="askDelete({{ $room->id }})">
                                {{ __('common.button.delete') }}
                            </button>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($showCreate)
        <x-wp-modal closeMethod="closeCreate">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('sense.add') }}</h2>
                    <x-wp-modal-close wire:click="closeCreate" />
                </div>
                @if ($unitChoices === [])
                    <p class="wp-text-body">{{ __('sense.no_units') }}</p>
                @else
                    <label class="wp-label" for="sense-unit">{{ __('sense.fields.room') }}</label>
                    <select id="sense-unit" class="wp-input" wire:model="unitId">
                        <option value="">{{ __('sense.fields.choose') }}</option>
                        @foreach ($unitChoices as $choice)
                            <option value="{{ $choice['id'] }}">{{ $choice['label'] }}</option>
                        @endforeach
                    </select>
                    @error('unitId') <p class="wp-muted">{{ $message }}</p> @enderror
                    <div class="wp-cluster">
                        <button type="button" class="btn btn--primary" wire:click="createPoint">{{ __('common.button.save') }}</button>
                        <button type="button" class="btn btn--ghost" wire:click="closeCreate">{{ __('common.button.cancel') }}</button>
                    </div>
                @endif
            </div>
        </x-wp-modal>
    @endif

    @if ($showDelete)
        <x-wp-modal closeMethod="closeDelete">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('sense.delete_title') }}</h2>
                    <x-wp-modal-close wire:click="closeDelete" />
                </div>
                <p class="wp-text-body">{{ __('sense.delete_body') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--danger" wire:click="deletePoint">{{ __('common.button.delete') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="closeDelete">{{ __('common.button.cancel') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
