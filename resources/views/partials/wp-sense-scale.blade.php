@php
    $alpine = $scale->alpine($pointId);
@endphp
<div class="wp-sense-metric"
     x-data="wpSenseScale(@js($alpine))"
     @pointermove.window="moveDrag($event)"
     @pointerup.window="endDrag()"
     @pointercancel.window="endDrag()">
    <div class="wp-sense-metric__head">
        <span class="wp-sense-metric__icon" aria-hidden="true">
            @if ($scale->metric === 'temperature')
                <svg viewBox="0 0 24 24" class="wp-sense-svg"><path fill="currentColor" d="M12 2a3 3 0 0 0-3 3v8.1a5 5 0 1 0 6 0V5a3 3 0 0 0-3-3Zm1 12.8V6h-2v8.8a3 3 0 1 0 2 0Z"/></svg>
            @elseif ($scale->metric === 'humidity')
                <svg viewBox="0 0 24 24" class="wp-sense-svg"><path fill="currentColor" d="M12 2.2S5.5 9 5.5 14.2a6.5 6.5 0 0 0 13 0C18.5 9 12 2.2 12 2.2Z"/></svg>
            @elseif ($scale->metric === 'co2')
                <span class="wp-sense-badge">CO₂</span>
            @else
                <svg viewBox="0 0 24 24" class="wp-sense-svg"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M3 14c3-6 6-6 9 0s6 6 9 0M3 9c3-6 6-6 9 0s6 6 9 0"/></svg>
            @endif
        </span>
        <span class="wp-sense-metric__title">{{ $scale->title }}</span>
        <span class="wp-sense-metric__value">{{ $scale->valueLabel }}</span>
    </div>

    <div class="wp-sense-track" x-ref="track" :style="trackStyle()">
        <template x-if="mode === 'band'">
            <button type="button" class="wp-sense-handle" :style="`left:${percent(low)}%; background:${colors.low}`"
                    @pointerdown.prevent="startDrag('low', $event)" :aria-label="label(low)"></button>
        </template>
        <template x-if="mode === 'band'">
            <button type="button" class="wp-sense-handle" :style="`left:${percent(target)}%; background:${colors.target}`"
                    @pointerdown.prevent="startDrag('target', $event)" :aria-label="label(target)"></button>
        </template>
        <button type="button" class="wp-sense-handle" :style="`left:${percent(high)}%; background:${colors.high}`"
                @pointerdown.prevent="startDrag('high', $event)" :aria-label="label(high)"></button>
    </div>

    <div class="wp-sense-marks">
        <template x-if="mode === 'band'">
            <span class="wp-sense-mark" :style="`left:${percent(low)}%; color:${colors.low}`">
                <button type="button" x-show="editing !== 'low'" @click="beginEdit('low')" x-text="label(low) + (metric === 'temperature' ? ' °C' : ' %')"></button>
                <input x-show="editing === 'low'" x-ref="editLow" class="wp-sense-input" x-model="draft" inputmode="decimal"
                       @keydown.enter.prevent="commitEdit()" @blur="commitEdit()">
            </span>
        </template>
        <template x-if="mode === 'band'">
            <span class="wp-sense-mark" :style="`left:${percent(target)}%; color:${colors.target}`">
                <button type="button" x-show="editing !== 'target'" @click="beginEdit('target')" x-text="label(target) + (metric === 'temperature' ? ' °C' : ' %')"></button>
                <input x-show="editing === 'target'" x-ref="editTarget" class="wp-sense-input" x-model="draft" inputmode="decimal"
                       @keydown.enter.prevent="commitEdit()" @blur="commitEdit()">
            </span>
        </template>
        <span class="wp-sense-mark wp-sense-mark--end" :style="`left:${percent(high)}%; color:${colors.high}`">
            <button type="button" x-show="editing !== 'high'" @click="beginEdit('high')"
                    x-text="label(high) + (metric === 'temperature' ? ' °C' : (metric === 'humidity' ? ' %' : (metric === 'co2' ? ' ppm' : '')))"></button>
            <input x-show="editing === 'high'" x-ref="editHigh" class="wp-sense-input" x-model="draft" inputmode="decimal"
                   @keydown.enter.prevent="commitEdit()" @blur="commitEdit()">
        </span>
    </div>
</div>
