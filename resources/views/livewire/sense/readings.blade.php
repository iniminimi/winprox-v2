<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('sense.readings.title', ['place' => $place])"
                :subtitle="__('sense.readings.subtitle')"
                help-page="sense.readings"
            />
            @if ($backUrl)
                <p>
                    <a href="{{ $backUrl }}" class="btn btn--ghost btn--sm">← {{ __('sense.readings.back') }}</a>
                </p>
            @endif
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster wp-cluster--wrap">
            <label class="wp-label" for="sense-location">{{ __('sense.readings.location') }}</label>
            <select id="sense-location" class="wp-select" wire:model.live="locationId">
                @foreach ($locations as $location)
                    <option value="{{ $location['id'] }}">{{ $location['name'] }}</option>
                @endforeach
            </select>
            <label class="wp-label" for="sense-screen">{{ __('sense.readings.screen') }}</label>
            <select id="sense-screen" class="wp-select" wire:model.live="screenId">
                @foreach ($screensHere as $screen)
                    <option value="{{ $screen->sensePointId }}">{{ $screen->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="wp-cluster wp-cluster--wrap">
            <label class="wp-label" for="sense-from">{{ __('sense.readings.from') }}</label>
            <input type="date" id="sense-from" class="wp-input wp-input--auto" wire:model.live="from">
            <label class="wp-label" for="sense-until">{{ __('sense.readings.until') }}</label>
            <input type="date" id="sense-until" class="wp-input wp-input--auto" wire:model.live="until">
            @if ($exportUrl && $printUrl)
                <x-wp-list-export :csv-url="$exportUrl" :print-url="$printUrl" :file-label="__('sense.readings.excel')" />
            @endif
        </div>
        @if ($periodError)
            <p class="wp-error">{{ $periodError }}</p>
        @endif
        <p class="wp-hint">{{ __('sense.readings.hint') }}</p>
    </div>

    <div class="wp-card wp-card-pad wp-stack-tight">
        @if ($readings === null || $readings->isEmpty())
            <p class="wp-muted">{{ __('sense.readings.empty') }}</p>
        @else
            @foreach ($readings as $reading)
                <p class="wp-text-body" wire:key="sense-reading-{{ $reading->id }}">{{ \App\Support\Sense\SenseReadingLine::text($reading) }}</p>
            @endforeach
            @if ($readings->hasPages())
                <nav class="wp-pagination" aria-label="{{ __('common.pagination.nav_label') }}">
                    <div class="wp-pagination__pages">
                        <button type="button" class="wp-pagination__control" wire:click="previousPage" @disabled($readings->onFirstPage()) aria-label="{{ __('common.pagination.previous') }}">‹</button>
                        <button type="button" class="wp-pagination__control" wire:click="nextPage" @disabled(! $readings->hasMorePages()) aria-label="{{ __('common.pagination.next') }}">›</button>
                    </div>
                </nav>
            @endif
        @endif
    </div>
</div>
