<div class="wp-stack">
    <x-wp-page-head-title
        icon="qr"
        :title="__('qr.connect.title')"
        :subtitle="__('qr.connect.subtitle')"
    />

    @if ($showSuccess && $gpsCaptureSuccess)
        <div class="wp-card wp-card-pad wp-card--success">
            <div class="wp-stack">
                <h2>{{ __('qr.connect.gps_success_title') }}</h2>
                <p>{{ __('qr.connect.gps_success_message') }}</p>
                <button type="button" class="btn btn--primary" wire:click="redirectToUnit">
                    {{ __('qr.connect.go_to_unit') }}
                </button>
            </div>
        </div>
    @elseif ($showSuccess && $showGpsCapture)
        <div class="wp-card wp-card-pad wp-stack" x-data="gpsCapture()" x-init="init()">
            <div class="wp-card-section">
                <h2>{{ __('qr.connect.gps_title') }}</h2>
                <p class="wp-muted">{{ __('qr.connect.gps_hint') }}</p>
            </div>

            <div class="wp-card-section">
                <div x-show="!hasPosition && !error" class="wp-stack-tight">
                    <p class="wp-muted">{{ __('qr.connect.gps_loading') }}</p>
                    <div class="wp-progress-bar">
                        <div class="wp-progress-bar__fill" style="width: 60%"></div>
                    </div>
                </div>

                <div x-show="error" class="wp-card wp-card--warning wp-card-pad">
                    <p class="wp-error" x-text="error"></p>
                    <button type="button" class="btn btn--ghost btn--sm" @click="retry()">
                        {{ __('qr.connect.gps_retry') }}
                    </button>
                </div>

                <div x-show="hasPosition && !error" class="wp-stack-tight">
                    <p class="wp-text-body">
                        <strong>{{ __('qr.connect.gps_found') }}</strong><br>
                        <span class="wp-muted">Lat: <span x-text="latitude.toFixed(6)"></span>, Lng: <span x-text="longitude.toFixed(6)"></span></span>
                    </p>
                </div>

                @error('gps')
                    <p class="wp-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="wp-card-actions">
                <button
                    type="button"
                    class="btn btn--primary"
                    wire:click="saveGps"
                    :disabled="!hasPosition"
                    x-on:click.prevent="$wire.latitude = latitude; $wire.longitude = longitude; $wire.saveGps()"
                >
                    {{ __('qr.connect.gps_save') }}
                </button>
                <button type="button" class="btn btn--ghost" wire:click="skipGps">
                    {{ __('qr.connect.gps_skip') }}
                </button>
            </div>
        </div>

        <script>
            function gpsCapture() {
                return {
                    latitude: null,
                    longitude: null,
                    hasPosition: false,
                    error: null,
                    init() {
                        this.getPosition();
                    },
                    getPosition() {
                        if (!navigator.geolocation) {
                            this.error = '{{ __('qr.connect.gps_not_supported') }}';
                            return;
                        }
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                this.latitude = position.coords.latitude;
                                this.longitude = position.coords.longitude;
                                this.hasPosition = true;
                                this.error = null;
                            },
                            (err) => {
                                this.error = err.message || '{{ __('qr.connect.gps_error') }}';
                                this.hasPosition = false;
                            },
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                        );
                    },
                    retry() {
                        this.error = null;
                        this.hasPosition = false;
                        this.getPosition();
                    }
                };
            }
        </script>
    @elseif ($showSuccess)
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
