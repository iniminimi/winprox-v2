<div class="wp-stack">
    <x-wp-page-head-title
        icon="qr"
        :title="__('qr.connect.title')"
        :subtitle="__('qr.connect.subtitle')"
    />

    @if ($showSuccess)
        <div class="wp-card wp-card-pad wp-card--success">
            <div class="wp-stack">
                <h2>{{ __('qr.connect.success_title') }}</h2>
                <p>{{ __('qr.connect.success_message', ['sticker' => $qrCode->sticker_number]) }}</p>
                <button type="button" class="btn btn--primary" wire:click="redirectToUnit">
                    {{ __('qr.connect.go_to_unit') }}
                </button>
            </div>
        </div>
    @else
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-card-section">
                <h3>{{ __('qr.connect.qr_info') }}</h3>
                <p class="wp-muted">{{ __('qr.connect.sticker_number') }} : <code>{{ $qrCode->sticker_number }}</code></p>
                <p class="wp-muted">{{ __('qr.connect.status') }} : {{ __($qrCode->status->labelKey()) }}</p>
            </div>

            <div class="wp-card-section">
                <label class="wp-label" for="unit-search">{{ __('qr.connect.search_units') }}</label>
                <input
                    id="unit-search"
                    type="search"
                    class="wp-input"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('qr.connect.search_placeholder') }}"
                    autocomplete="off"
                >

                @error('selectedUnitId')
                    <p class="wp-error">{{ $message }}</p>
                @enderror

                @if ($this->units->isEmpty())
                    <p class="wp-muted">{{ __('qr.connect.no_units') }}</p>
                @else
                    <div class="wp-list-plain wp-stack-tight">
                        @foreach ($this->units as $unit)
                            <label class="wp-list-row wp-list-row--interactive">
                                <input
                                    type="radio"
                                    name="unit"
                                    value="{{ $unit->id }}"
                                    wire:model="selectedUnitId"
                                >
                                <div class="wp-grow">
                                    <strong>{{ $unit->name }}</strong>
                                    @if ($unit->location)
                                        <span class="wp-muted wp-text-sm"> — {{ $unit->location->name }}</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>

                    {{ $this->units->links() }}
                @endif
            </div>

            <div class="wp-card-actions">
                <button 
                    type="button" 
                    class="btn btn--primary" 
                    wire:click="link"
                    :disabled="$selectedUnitId === null"
                >
                    {{ __('qr.connect.link_button') }}
                </button>
            </div>
        </div>
    @endif
</div>
