<div class="wp-cluster wp-cluster--wrap">
    <label class="wp-label" for="sense-display-locale">{{ __('screen.sense.language') }}</label>
    <select id="sense-display-locale" class="wp-input wp-input--auto" wire:change="saveSenseLocale($event.target.value)">
        @foreach (config('locales.supported') as $code)
            <option value="{{ $code }}" @selected(($screen->sensePoint->display_locale ?: config('locales.default')) === $code)>{{ __('screen.sense.languages.'.$code) }}</option>
        @endforeach
    </select>
    <span class="wp-hint">{{ __('screen.sense.language_hint') }}</span>
</div>
