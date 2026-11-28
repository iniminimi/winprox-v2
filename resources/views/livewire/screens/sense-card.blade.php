<div>
    @if ($bandError)
        <p class="wp-flash wp-flash--danger">{{ $bandError }}</p>
    @endif

    @if ($room)
        <article class="wp-sense-room" wire:key="sense-room-{{ $room->id }}-{{ $room->locale }}-{{ $room->temperature->low }}-{{ $room->temperature->target }}-{{ $room->temperature->high }}-{{ $room->humidity->low }}-{{ $room->humidity->target }}-{{ $room->humidity->high }}-{{ $room->co2->high }}-{{ $room->voc->high }}-{{ $room->temperature->value }}-{{ $room->humidity->value }}-{{ $room->co2->value }}-{{ $room->voc->value }}">
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

            <div class="wp-cluster wp-sense-tools">
                @if ($readingsUrl !== '')
                    <a href="{{ $readingsUrl }}" class="btn btn--ghost btn--sm">{{ __('sense.readings.open') }}</a>
                @endif
                <button type="button" @class(['btn', 'btn--sm', 'btn--primary' => $room->preset === 'winter', 'btn--ghost' => $room->preset !== 'winter']) wire:click="applyPreset({{ $room->id }}, 'winter')">
                    {{ __('sense.presets.winter') }}
                </button>
                <button type="button" @class(['btn', 'btn--sm', 'btn--primary' => $room->preset === 'summer', 'btn--ghost' => $room->preset !== 'summer']) wire:click="applyPreset({{ $room->id }}, 'summer')">
                    {{ __('sense.presets.summer') }}
                </button>
            </div>
        </article>
    @endif
</div>
