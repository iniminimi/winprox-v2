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
        {{-- GPS Capture UI - Simplified --}}
        <div class="wp-card wp-card-pad wp-card--info" x-data="{ 
            lat: null, 
            lng: null, 
            found: false, 
            error: null, 
            manual: false,
            manualLat: '',
            manualLng: ''
        }" x-init="
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => { lat = pos.coords.latitude; lng = pos.coords.longitude; found = true; },
                    (err) => { 
                        error = err.code === 1 ? '{{ __('qr.connect.gps_denied') }}' : '{{ __('qr.connect.gps_error') }}';
                        manual = true;
                    },
                    { enableHighAccuracy: true, timeout: 15000 }
                );
            } else {
                error = '{{ __('qr.connect.gps_not_supported') }}';
                manual = true;
            }
        " wire:key="gps-capture">

            <div class="wp-stack-tight" style="margin-bottom: 0.75rem;">
                <h2 class="wp-section-title">📍 {{ __('qr.connect.gps_title') }}</h2>
                <p class="wp-muted">{{ __('qr.connect.gps_hint') }}</p>
            </div>

            {{-- Searching --}}
            <div x-show="!found && !error && !manual" class="wp-stack" style="text-align: center; padding: 1rem 0;">
                <div style="font-size: 2rem; animation: spin 1s linear infinite;">🔄</div>
                <p class="wp-text-body" style="color: var(--wp-info-text);">{{ __('qr.connect.gps_loading') }}</p>
            </div>

            {{-- Found --}}
            <div x-show="found" class="wp-card wp-card--success-accent wp-card-pad" style="text-align: center;">
                <p class="wp-text-body">✅ <strong>{{ __('qr.connect.gps_found') }}</strong></p>
                <p class="wp-muted" style="font-family: monospace; font-size: 0.85em;" x-text="lat?.toFixed(6) + ', ' + lng?.toFixed(6)"></p>
            </div>

            {{-- Error --}}
            <div x-show="error" class="wp-card wp-card--warning wp-card-pad">
                <p class="wp-error">⚠️ <span x-text="error"></span></p>
                <button type="button" class="btn btn--ghost btn--sm" @click="error = null; manual = true;">
                    {{ __('qr.connect.gps_retry') }}
                </button>
            </div>

            {{-- Manual Entry --}}
            <div x-show="manual" class="wp-stack-tight" style="margin-top: 0.75rem;">
                <p class="wp-muted" style="text-align: center;">{{ __('qr.connect.gps_or_enter_manual') }}</p>
                <div class="wp-cluster" style="gap: 0.5rem;">
                    <input type="number" step="any" x-model="manualLat" placeholder="{{ __('qr.connect.gps_latitude_placeholder') }}" class="wp-input">
                    <input type="number" step="any" x-model="manualLng" placeholder="{{ __('qr.connect.gps_longitude_placeholder') }}" class="wp-input">
                </div>
                <button type="button" class="btn btn--surface btn--sm btn--block" 
                    @click="lat = parseFloat(manualLat); lng = parseFloat(manualLng); if (!isNaN(lat) && !isNaN(lng)) { found = true; manual = false; } else { error = '{{ __('qr.connect.gps_invalid_coords') }}'; }">
                    {{ __('qr.connect.gps_use_manual') }}
                </button>
            </div>

            @error('gps')
                <p class="wp-error">{{ $message }}</p>
            @enderror

            {{-- Actions --}}
            <div class="wp-stack-tight" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--wp-info-border);">
                <button type="button" class="btn btn--primary btn--block" :disabled="!found"
                    @click="$wire.latitude = lat; $wire.longitude = lng; $wire.saveGps()">
                    <span x-show="!found">⏳ {{ __('qr.connect.gps_waiting') }}</span>
                    <span x-show="found">💾 {{ __('qr.connect.gps_save') }}</span>
                </button>

                <div class="wp-cluster" style="justify-content: space-between;">
                    <button type="button" class="btn btn--ghost btn--sm" @click="manual = !manual; error = null;">
                        <span x-show="!manual">⌨️ {{ __('qr.connect.gps_manual_button') }}</span>
                        <span x-show="manual">🔄 {{ __('qr.connect.gps_auto_button') }}</span>
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="skipGps">
                        {{ __('qr.connect.gps_skip') }} →
                    </button>
                </div>
            </div>
        </div>

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
