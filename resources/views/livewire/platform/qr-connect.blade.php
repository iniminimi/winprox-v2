<div class="wp-stack">
    <x-wp-page-head-title
        icon="qr"
        :title="__('qr.connect.title')"
        :subtitle="__('qr.connect.subtitle')"
    />

    {{-- DEBUG: Always show state for troubleshooting --}}
    @if ($showSuccess)
        @php
            $debugUnit = $linkedUnit ?? ($selectedUnitId ? \App\Models\Unit::find($selectedUnitId) : null);
        @endphp
        <div class="wp-card wp-card-pad" style="background: #f0f9ff; border: 2px dashed #3b82f6;">
            <p style="font-family: monospace; font-size: 12px; margin: 0;">
                <strong>DEBUG:</strong><br>
                showSuccess: {{ $showSuccess ? 'true' : 'false' }}<br>
                showGpsCapture: {{ $showGpsCapture ? 'true' : 'false' }}<br>
                gpsCaptureSuccess: {{ $gpsCaptureSuccess ? 'true' : 'false' }}<br>
                selectedUnitId: {{ $selectedUnitId ?? 'null' }}<br>
                linkedUnit: {{ $linkedUnit ? 'set' : 'null' }}<br>
                @if ($debugUnit)
                    unit.id: {{ $debugUnit->id }}<br>
                    unit.latitude: {{ $debugUnit->latitude ?? 'null' }}<br>
                    unit.longitude: {{ $debugUnit->longitude ?? 'null' }}<br>
                    unit.hasGps(): {{ $debugUnit->hasGps() ? 'true' : 'false' }}<br>
                    <strong>Should show GPS:</strong> {{ !$debugUnit->hasGps() ? 'YES' : 'NO (already has GPS)' }}
                @else
                    No unit found for debug
                @endif
            </p>
        </div>
    @endif

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
        <div class="wp-card wp-card-pad wp-card--accent wp-stack" x-data="gpsCapture()" x-init="init()">
            <div class="wp-card-section">
                <h2 style="margin-bottom: 0.5rem;">📍 {{ __('qr.connect.gps_title') }}</h2>
                <p class="wp-text-body">{{ __('qr.connect.gps_hint') }}</p>
            </div>

            <div class="wp-card-section">
                {{-- Loading state --}}
                <div x-show="loading" class="wp-stack-tight" x-cloak>
                    <p class="wp-text-body" style="text-align: center; padding: 1rem 0;">
                        <span style="display: inline-block; animation: pulse 1.5s infinite;">📡</span><br>
                        {{ __('qr.connect.gps_loading') }}
                    </p>
                    <div class="wp-progress-bar">
                        <div class="wp-progress-bar__fill" style="width: 70%; animation: progressPulse 1s infinite;"></div>
                    </div>
                </div>

                {{-- Error state --}}
                <div x-show="error" class="wp-card wp-card--warning wp-card-pad" style="margin-top: 0.5rem;" x-cloak>
                    <p class="wp-error" style="margin-bottom: 0.5rem;">⚠️ <span x-text="error"></span></p>
                    <button type="button" class="btn btn--ghost btn--sm" @click="retry()">
                        {{ __('qr.connect.gps_retry') }}
                    </button>
                </div>

                {{-- Success state - position found --}}
                <div x-show="hasPosition && !error" class="wp-card wp-card--success wp-card-pad" style="margin-top: 0.5rem;" x-cloak>
                    <p class="wp-text-body" style="text-align: center;">
                        ✅ <strong>{{ __('qr.connect.gps_found') }}</strong><br>
                        <span class="wp-muted" style="font-family: monospace; font-size: 0.9em;">
                            Lat: <span x-text="latitude ? latitude.toFixed(6) : '-'"></span><br>
                            Lng: <span x-text="longitude ? longitude.toFixed(6) : '-'"></span>
                        </span>
                    </p>
                </div>

                {{-- Manual entry fallback --}}
                <div x-show="showManual" class="wp-stack-tight" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--wp-border);" x-cloak>
                    <p class="wp-muted" style="text-align: center; margin-bottom: 0.5rem;">{{ __('qr.connect.gps_or_enter_manual') }}</p>
                    <div class="wp-cluster" style="gap: 0.5rem;">
                        <input type="number" step="any" x-model="manualLat" placeholder="Latitude (bijv. 51.123)" class="wp-input" style="flex: 1;">
                        <input type="number" step="any" x-model="manualLng" placeholder="Longitude (bijv. 4.567)" class="wp-input" style="flex: 1;">
                    </div>
                    <button type="button" class="btn btn--surface btn--sm btn--block" @click="useManual()">
                        {{ __('qr.connect.gps_use_manual') }}
                    </button>
                </div>

                @error('gps')
                    <p class="wp-error" style="margin-top: 0.5rem;">{{ $message }}</p>
                @enderror
            </div>

            <div class="wp-card-actions" style="flex-direction: column; gap: 0.5rem;">
                <button
                    type="button"
                    class="btn btn--primary btn--block"
                    wire:click="saveGps"
                    :disabled="!hasPosition"
                    x-on:click.prevent="$wire.latitude = latitude; $wire.longitude = longitude; $wire.saveGps()"
                >
                    <span x-show="!hasPosition" style="opacity: 0.7;">⏳ {{ __('qr.connect.gps_waiting') }}</span>
                    <span x-show="hasPosition">💾 {{ __('qr.connect.gps_save') }}</span>
                </button>
                <div class="wp-cluster" style="justify-content: space-between; width: 100%;">
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

        <style>
            @keyframes pulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.5; transform: scale(1.2); }
            }
            @keyframes progressPulse {
                0% { width: 30%; }
                50% { width: 70%; }
                100% { width: 30%; }
            }
        </style>

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
