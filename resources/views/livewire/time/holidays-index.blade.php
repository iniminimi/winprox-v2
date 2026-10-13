<div class="wp-stack" data-manual-capture="time-holidays">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('time.holidays.title')"
                help-page="time.holidays"
                :subtitle="__('time.holidays.subtitle')"
            />
        </div>
    </div>

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-stack-tight">
            <h2 class="wp-h3">{{ __('time.schedule.month_norm.holidays_title') }}</h2>
            <p class="wp-hint">{{ __('time.schedule.month_norm.holidays_hint') }}</p>
        </div>
        <div class="wp-cluster wp-cluster--tight">
            <button type="button" class="btn btn--ghost btn--sm" wire:click="setYear({{ $holidayYear - 1 }})" @disabled($holidayYear <= 2020)>{{ __('time.schedule.month_norm.previous_year') }}</button>
            <span class="wp-label">{{ $holidayYear }}</span>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="setYear({{ $holidayYear + 1 }})" @disabled($holidayYear >= 2040)>{{ __('time.schedule.month_norm.next_year') }}</button>
        </div>
        @error('agreements') <p class="wp-error">{{ $message }}</p> @enderror
        @foreach ($agreements as $index => $holiday)
            <div class="wp-cluster">
                <label class="wp-label" for="holidayEffect{{ $index }}">{{ __('time.schedule.month_norm.holidays.'.$holiday['code']) }} · {{ $holiday['date'] }}</label>
                <select id="holidayEffect{{ $index }}" class="wp-select wp-holiday-row__select" wire:model.live="agreements.{{ $index }}.effect">
                    <option value="">{{ __('time.schedule.month_norm.effect_unset') }}</option>
                    <option value="none">{{ __('time.schedule.month_norm.effects.none') }}</option>
                    <option value="dropped">{{ __('time.schedule.month_norm.effects.dropped') }}</option>
                    <option value="replaced">{{ __('time.schedule.month_norm.effects.replaced') }}</option>
                </select>
                @if (($holiday['effect'] ?? '') === 'replaced')
                    <input class="wp-input wp-holiday-row__date" type="date" wire:model="agreements.{{ $index }}.replacement_date" aria-label="{{ __('time.schedule.month_norm.replacement_date') }}">
                @endif
            </div>
        @endforeach
        @if (session('time_flash'))
            <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
        @endif
        @can('create', \App\Models\TenantHolidayAgreement::class)
            <div class="wp-cluster wp-cluster--end">
                <button type="button" class="btn btn--primary" wire:click="save">{{ __('time.holidays.save') }}</button>
            </div>
        @endcan
    </div>
</div>
