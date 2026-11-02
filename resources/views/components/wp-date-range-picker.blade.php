@props([
    'min' => null,
    'maxDays' => 31,
    'locale' => null,
])

@php
    $dateLang = \App\Support\Translation\LocaleSupport::dateInputLang($locale);
    $minDate = $min ?: now()->toDateString();
    $maxDays = max(1, (int) $maxDays);
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:model')->class('wp-date-range') }}
    wire:ignore.self
    x-data="{
        from: @entangle($attributes->wire('model.from')),
        to: @entangle($attributes->wire('model.to')),
        min: @js($minDate),
        maxDays: {{ $maxDays }},
        locale: @js($dateLang),
        viewYear: 0,
        viewMonth: 0,
        pickingEnd: false,
        init() {
            const seed = this.from || this.min;
            const d = this.parse(seed) || this.parse(this.min);
            this.viewYear = d.getFullYear();
            this.viewMonth = d.getMonth();
            this.pickingEnd = !!(this.from && this.to && this.from === this.to);
            this.$watch('from', () => this.syncView());
        },
        syncView() {
            const d = this.parse(this.from || this.min);
            if (! d) return;
            this.viewYear = d.getFullYear();
            this.viewMonth = d.getMonth();
        },
        parse(iso) {
            if (! iso || typeof iso !== 'string' || iso.length < 10) return null;
            const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
            if (! y || ! m || ! d) return null;
            return new Date(y, m - 1, d);
        },
        ymd(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        },
        addDays(iso, days) {
            const d = this.parse(iso);
            if (! d) return iso;
            d.setDate(d.getDate() + days);
            return this.ymd(d);
        },
        monthLabel() {
            return new Intl.DateTimeFormat(this.locale, { month: 'long', year: 'numeric' }).format(new Date(this.viewYear, this.viewMonth, 1));
        },
        weekdays() {
            return Array.from({ length: 7 }, (_, i) => {
                const d = new Date(Date.UTC(2024, 0, 1 + i));
                return new Intl.DateTimeFormat(this.locale, { weekday: 'short', timeZone: 'UTC' }).format(d);
            });
        },
        cells() {
            const first = new Date(this.viewYear, this.viewMonth, 1);
            const pad = (first.getDay() + 6) % 7;
            const days = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const out = [];
            for (let i = 0; i < pad; i++) out.push(null);
            for (let day = 1; day <= days; day++) {
                out.push(this.ymd(new Date(this.viewYear, this.viewMonth, day)));
            }
            while (out.length % 7 !== 0) out.push(null);
            return out;
        },
        canGoPrev() {
            const prevLast = new Date(this.viewYear, this.viewMonth, 0);
            return this.ymd(prevLast) >= this.min;
        },
        isDisabled(iso) {
            return ! iso || iso < this.min;
        },
        isStart(iso) {
            return !! iso && iso === this.from;
        },
        isEnd(iso) {
            return !! iso && !! this.to && iso === this.to && this.to !== this.from;
        },
        inRange(iso) {
            if (! iso || ! this.from || ! this.to) return false;
            return iso > this.from && iso < this.to;
        },
        dayCount() {
            if (! this.from || ! this.to) return 0;
            const a = this.parse(this.from);
            const b = this.parse(this.to);
            if (! a || ! b) return 0;
            return Math.round((b - a) / 86400000) + 1;
        },
        summary() {
            if (! this.from) return '';
            const fmt = new Intl.DateTimeFormat(this.locale, { day: 'numeric', month: 'short' });
            const start = fmt.format(this.parse(this.from));
            if (! this.to || this.to === this.from) return start;
            return start + ' – ' + fmt.format(this.parse(this.to));
        },
        daysLabel() {
            const n = this.dayCount();
            if (n <= 0) return '';
            if (n === 1) return @js(__('time.portal.absence.period_one_day'));
            return @js(__('time.portal.absence.period_days')).replace(':count', String(n));
        },
        prevMonth() {
            if (! this.canGoPrev()) return;
            if (this.viewMonth === 0) { this.viewMonth = 11; this.viewYear--; }
            else this.viewMonth--;
        },
        nextMonth() {
            if (this.viewMonth === 11) { this.viewMonth = 0; this.viewYear++; }
            else this.viewMonth++;
        },
        pick(iso) {
            if (this.isDisabled(iso)) return;
            if (! this.from || ! this.pickingEnd) {
                this.from = iso;
                this.to = iso;
                this.pickingEnd = true;
                return;
            }
            if (iso < this.from) {
                this.to = this.from;
                this.from = iso;
            } else {
                this.to = iso;
            }
            if (this.dayCount() > this.maxDays) {
                this.to = this.addDays(this.from, this.maxDays - 1);
            }
            this.pickingEnd = false;
        }
    }"
>
    <div class="wp-date-range__summary" x-show="from" x-cloak>
        <strong class="wp-date-range__summary-dates" x-text="summary()"></strong>
        <span class="wp-muted" x-text="daysLabel()"></span>
    </div>
    <p class="wp-muted wp-date-range__hint">{{ __('time.portal.absence.period_hint') }}</p>

    <div class="wp-date-range__panel">
        <div class="wp-date-range__nav">
            <button
                type="button"
                class="wp-pagination__control"
                @click="prevMonth()"
                :disabled="! canGoPrev()"
                aria-label="{{ __('time.portal.absence.prev_month') }}"
            >‹</button>
            <p class="wp-date-range__month" x-text="monthLabel()"></p>
            <button
                type="button"
                class="wp-pagination__control"
                @click="nextMonth()"
                aria-label="{{ __('time.portal.absence.next_month') }}"
            >›</button>
        </div>

        <div class="wp-date-range__weekdays" aria-hidden="true">
            <template x-for="(label, i) in weekdays()" :key="'wd-'+i">
                <span x-text="label"></span>
            </template>
        </div>

        <div class="wp-date-range__grid" role="grid" aria-label="{{ __('time.portal.absence.period') }}">
            <template x-for="(iso, i) in cells()" :key="'cell-'+viewYear+'-'+viewMonth+'-'+i">
                <button
                    type="button"
                    class="wp-date-range__day"
                    :class="{
                        'wp-date-range__day--empty': !iso,
                        'wp-date-range__day--disabled': iso && isDisabled(iso),
                        'wp-date-range__day--start': iso && isStart(iso),
                        'wp-date-range__day--end': iso && isEnd(iso),
                        'wp-date-range__day--single': iso && isStart(iso) && (!to || to === from),
                        'wp-date-range__day--in-range': iso && inRange(iso),
                        'wp-date-range__day--today': iso === min
                    }"
                    :disabled="!iso || isDisabled(iso)"
                    :tabindex="iso && ! isDisabled(iso) ? 0 : -1"
                    @click="iso && pick(iso)"
                    x-text="iso ? Number(iso.slice(8, 10)) : ''"
                ></button>
            </template>
        </div>
    </div>
</div>
