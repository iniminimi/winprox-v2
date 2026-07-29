@props([])

@php
    $halfHourSlots = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $halfHourSlots[] = sprintf('%02d:00', $hour);
        $halfHourSlots[] = sprintf('%02d:30', $hour);
    }
@endphp

<div
    x-data="{
        value: @entangle($attributes->wire('model')),
        date: '',
        time: '',
        init() {
            this.fromValue();
            this.$watch('value', () => this.fromValue());
        },
        fromValue() {
            const raw = this.value || '';
            if (! raw.includes('T')) {
                this.date = '';
                this.time = '';
                return;
            }
            const [datePart, timePart = ''] = raw.split('T');
            this.date = datePart;
            const hhmm = timePart.slice(0, 5);
            const [hourRaw, minuteRaw] = hhmm.split(':');
            const hour = Number(hourRaw);
            const minute = Number(minuteRaw);
            if (! Number.isFinite(hour) || ! Number.isFinite(minute)) {
                this.time = '';
                return;
            }
            const snapped = String(hour).padStart(2, '0') + ':' + (minute < 30 ? '00' : '30');
            this.time = snapped;
            if (snapped !== hhmm) {
                this.sync();
            }
        },
        sync() {
            this.value = (this.date && this.time) ? (this.date + 'T' + this.time) : '';
        }
    }"
    {{ $attributes->whereDoesntStartWith('wire:model')->class('wp-half-hour-datetime') }}
>
    <x-wp-date-input class="wp-input" x-model="date" @change="sync()" />
    <select class="wp-select" x-model="time" @change="sync()">
        <option value="">—</option>
        @foreach ($halfHourSlots as $slot)
            <option value="{{ $slot }}">{{ $slot }}</option>
        @endforeach
    </select>
</div>
