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
        <div class="wp-card wp-card-pad wp-card--info" x-data="gpsCapture()" x-init="init()">
            <div class="wp-stack-tight" style="margin-bottom: 0.75rem;">
                <h2 class="wp-section-title">📍 {{ __('qr.connect.gps_title') }}</h2>
                <p class="wp-muted">{{ __('qr.connect.gps_hint') }}</p>
            </div>

            {{-- Loading State --}}
            <div x-show="loading" x-cloak class="wp-stack" style="text-align: center; padding: 1rem 0;">
                <div class="wp-animate-pulse" style="font-size: 2.5rem;">📡</div>
                <p class="wp-text-body" style="color: var(--wp-info-text);">{{ __('qr.connect.gps_loading') }}</p>
                <div class="wp-progress-track">
                    <div class="wp-progress-fill wp-progress-fill--animated"></div>
                </div>
            </div>

            {{-- Error State --}}
            <div x-show="error" x-cloak class="wp-card wp-card--warning wp-card-pad">
                <p class="wp-error">⚠️ <span x-text="error"></span></p>
                <button type="button" class="btn btn--ghost btn--sm" @click="retry()">
                    {{ __('qr.connect.gps_retry') }}
                </button>
            </div>

            {{-- Success State --}}
            <div x-show="hasPosition && !error" x-cloak class="wp-card wp-card--success-accent wp-card-pad" style="text-align: center;">
                <p class="wp-text-body">
                    ✅ <strong>{{ __('qr.connect.gps_found') }}</strong><br>
                    <span class="wp-muted" style="font-family: monospace; font-size: 0.85em;">
                        Lat: <span x-text="latitude.toFixed(6)"></span><br>
                        Lng: <span x-text="longitude.toFixed(6)"></span>
                    </span>
                </p>
            </div>

            {{-- Manual Entry --}}
            <div x-show="showManual" x-cloak class="wp-stack-tight" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 2px dashed var(--wp-info-border);">
                <p class="wp-muted" style="text-align: center;">{{ __('qr.connect.gps_or_enter_manual') }}</p>
                <div class="wp-cluster" style="gap: 0.5rem;">
                    <input type="number" step="any" x-model="manualLat" placeholder="Latitude" class="wp-input">
                    <input type="number" step="any" x-model="manualLng" placeholder="Longitude" class="wp-input">
                </div>
                <button type="button" class="btn btn--surface btn--sm btn--block" @click="useManual()">
                    {{ __('qr.connect.gps_use_manual') }}
                </button>
            </div>

            @error('gps')
                <p class="wp-error">{{ $message }}</p>
            @enderror

            {{-- Actions --}}
            <div class="wp-stack-tight" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--wp-info-border);">
                <button
                    type="button"
                    class="btn btn--primary btn--block"
                    :class="hasPosition ? '' : 'btn--disabled'"
                    :disabled="!hasPosition"
                    @click.prevent="$wire.latitude = latitude; $wire.longitude = longitude; $wire.saveGps()"
                >
                    <span x-show="!hasPosition">⏳ {{ __('qr.connect.gps_waiting') }}</span>
                    <span x-show="hasPosition">💾 {{ __('qr.connect.gps_save') }}</span>
                </button>

                <div class="wp-cluster" style="justify-content: space-between;">
                    <button type="button" class="btn btn--ghost btn--sm" @click="toggleManual()">
                        <span x-show="!showManual">⌨️ {{ __('qr.connect.gps_manual_button') }}</span>
                        <span x-show="showManual">🔄 {{ __('qr.connect.gps_auto_button') }}</span>
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="skipGps">
                        {{ __('qr.connect.gps_skip') }} →
                    </button>
                </div>
            </div>
        </div>

        <script>
            function gpsCapture() {
                return {
                    latitude: null,
                    longitude: null,
                    hasPosition: false,
                    error: null,
                    loading: true,
                    showManual: false,
                    manualLat: '',
                    manualLng: '',
                    init() {
                        this.getPosition();
                    },
                    getPosition() {
                        this.loading = true;
                        this.error = null;
                        this.hasPosition = false;

                        if (!navigator.geolocation) {
                            this.error = '{{ __('qr.connect.gps_not_supported') }}';
                            this.loading = false;
                            this.showManual = true;
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                this.latitude = position.coords.latitude;
                                this.longitude = position.coords.longitude;
                                this.hasPosition = true;
                                this.error = null;
                                this.loading = false;
                                this.showManual = false;
                            },
                            (err) => {
                                console.error('GPS Error:', err);
                                let message = '{{ __('qr.connect.gps_error') }}';
                                if (err.code === 1) message = '{{ __('qr.connect.gps_denied') }}';
                                if (err.code === 2) message = '{{ __('qr.connect.gps_unavailable') }}';
                                if (err.code === 3) message = '{{ __('qr.connect.gps_timeout') }}';
                                this.error = message;
                                this.loading = false;
                                this.hasPosition = false;
                                this.showManual = true;
                            },
                            { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
                        );
                    },
                    retry() {
                        this.showManual = false;
                        this.getPosition();
                    },
                    toggleManual() {
                        this.showManual = !this.showManual;
                        if (this.showManual) {
                            this.error = null;
                        }
                    },
                    useManual() {
                        const lat = parseFloat(this.manualLat);
                        const lng = parseFloat(this.manualLng);

                        if (isNaN(lat) || isNaN(lng)) {
                            this.error = '{{ __('qr.connect.gps_invalid_coords') }}';
                            return;
                        }

                        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                            this.error = '{{ __('qr.connect.gps_out_of_range') }}';
                            return;
                        }

                        this.latitude = lat;
                        this.longitude = lng;
                        this.hasPosition = true;
                        this.error = null;
                        this.loading = false;
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
                {{-- DEBUG: Force GPS capture button --}}
                @if ($selectedUnitId)
                    @php
                        $forceUnit = \App\Models\Unit::find($selectedUnitId);
                    @endphp
                    @if ($forceUnit && !$forceUnit->hasGps())
                        <hr style="margin: 1rem 0; border: none; border-top: 1px dashed #ccc;">
                        <p class="wp-muted" style="font-size: 12px;">Unit heeft geen GPS. Forceer GPS capture:</p>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="$set('showGpsCapture', true)">
                            📍 Force GPS Capture
                        </button>
                    @endif
                @endif
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
