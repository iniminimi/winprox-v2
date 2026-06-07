<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                @php
                    $tenant = \App\Support\Tenancy::id() ? \App\Models\Tenant::find(\App\Support\Tenancy::id()) : null;
                    $logoUrl = $tenant ? $tenant->logoPublicUrl() : null;
                @endphp
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name ?? 'Logo' }}" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @else
                    <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @endif
            </span>
            <div class="wp-cluster wp-cluster--tight">
                <x-wp-page-help page="portal.unassigned_qr" />
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <p class="wp-muted">{{ __('portal.unassigned_qr.subtitle') }}</p>
    </div>

    @if ($flashMessage !== '')
        <div class="wp-flash">{{ $flashMessage }}</div>
    @endif

    @if ($showSuccess && $gpsCaptureSuccess)
        {{-- GPS saved successfully --}}
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
        {{-- GPS Capture UI - Alpine takes over immediately --}}
        <div x-data="gpsCapture()" x-init="init()" style="border: 3px solid #2563eb; border-radius: 1rem; background: #dbeafe; padding: 1rem; margin: 0.5rem 0;" wire:key="gps-capture-{{ $linkedUnit?->id ?? 'new' }}">
            <div style="margin-bottom: 0.75rem;">
                <h2 style="margin: 0 0 0.25rem 0; font-size: 1.1rem; font-weight: 700; color: #1e40af;">📍 {{ __('qr.connect.gps_title') }}</h2>
                <p style="margin: 0; font-size: 0.9rem; color: #3b82f6;">{{ __('qr.connect.gps_hint') }}</p>
            </div>

            {{-- Loading State --}}
            <template x-if="loading">
                <div style="text-align: center; padding: 1rem 0;">
                    <div style="font-size: 2.5rem; display: inline-block; animation: pulse 1.2s ease-in-out infinite;">📡</div>
                    <p style="margin: 0.75rem 0; color: #2563eb; font-weight: 500;">{{ __('qr.connect.gps_loading') }}</p>
                    <div style="height: 6px; background: #bfdbfe; border-radius: 3px; overflow: hidden; margin: 0.5rem 0;">
                        <div style="height: 100%; width: 60%; background: #2563eb; border-radius: 3px; animation: progressPulse 0.8s ease-in-out infinite;"></div>
                    </div>
                </div>
            </template>

            {{-- Error State --}}
            <template x-if="error">
                <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 0.5rem; padding: 0.75rem; margin-top: 0.5rem;">
                    <p style="color: #92400e; margin: 0 0 0.5rem 0; font-weight: 500;">⚠️ <span x-text="error"></span></p>
                    <button type="button" class="btn btn--ghost btn--sm" @click="retry()">
                        {{ __('qr.connect.gps_retry') }}
                    </button>
                </div>
            </template>

            {{-- Success State --}}
            <template x-if="hasPosition && !error">
                <div style="background: #d1fae5; border: 2px solid #10b981; border-radius: 0.5rem; padding: 0.75rem; margin-top: 0.5rem; text-align: center;">
                    <p style="margin: 0; color: #065f46; font-weight: 500;">
                        ✅ <strong>{{ __('qr.connect.gps_found') }}</strong><br>
                        <span style="color: #047857; font-family: monospace; font-size: 0.85em;">
                            Lat: <span x-text="latitude.toFixed(6)"></span><br>
                            Lng: <span x-text="longitude.toFixed(6)"></span>
                        </span>
                    </p>
                </div>
            </template>

            {{-- Manual Entry --}}
            <template x-if="showManual">
                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 2px dashed #60a5fa;">
                    <p style="text-align: center; margin: 0 0 0.5rem 0; color: #2563eb; font-size: 0.85rem;">{{ __('qr.connect.gps_or_enter_manual') }}</p>
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <input type="number" step="any" x-model="manualLat" placeholder="Latitude" style="flex: 1; padding: 0.5rem; border: 2px solid #60a5fa; border-radius: 0.5rem; background: white;">
                        <input type="number" step="any" x-model="manualLng" placeholder="Longitude" style="flex: 1; padding: 0.5rem; border: 2px solid #60a5fa; border-radius: 0.5rem; background: white;">
                    </div>
                    <button type="button" class="btn btn--surface btn--sm btn--block" @click="useManual()">
                        {{ __('qr.connect.gps_use_manual') }}
                    </button>
                </div>
            </template>

            @error('gps')
                <p class="wp-error" style="margin-top: 0.5rem;">{{ $message }}</p>
            @enderror

            {{-- Actions --}}
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #93c5fd;">
                <button
                    type="button"
                    style="width: 100%; padding: 0.75rem; border: none; border-radius: 0.5rem; background: #2563eb; color: white; font-weight: 600; cursor: pointer;"
                    :style="hasPosition ? 'background: #059669;' : 'background: #93c5fd; cursor: not-allowed;'"
                    :disabled="!hasPosition"
                    @click.prevent="$wire.latitude = latitude; $wire.longitude = longitude; $wire.saveGps()"
                >
                    <span x-show="!hasPosition">⏳ {{ __('qr.connect.gps_waiting') }}</span>
                    <span x-show="hasPosition">💾 {{ __('qr.connect.gps_save') }}</span>
                </button>

                <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
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
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.15); }
            }
            @keyframes progressPulse {
                0% { width: 20%; margin-left: 0; }
                50% { width: 60%; margin-left: 20%; }
                100% { width: 20%; margin-left: 80%; }
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
        {{-- Simple success without GPS --}}
        <div class="wp-card wp-card-pad wp-card--success">
            <div class="wp-stack">
                <h2>{{ __('qr.connect.success_title') }}</h2>
                <p>{{ __('qr.connect.success_message', ['sticker' => $qrCode->sticker_number]) }}</p>
                <button type="button" class="btn btn--primary" wire:click="redirectToUnit">
                    {{ __('qr.connect.go_to_unit') }}
                </button>
            </div>
        </div>
    @elseif ($showLogin)
        <div class="wp-card wp-card-pad wp-stack">
            <h1 class="wp-section-title">{{ __('portal.unassigned_qr.title') }}</h1>
            
            <div class="wp-card-section">
                <dl class="wp-key-value">
                    <dt>{{ __('portal.unassigned_qr.sticker_number') }}</dt>
                    <dd><code>{{ $qrCode->sticker_number }}</code></dd>
                </dl>
            </div>

            <div class="wp-card-section">
                <p class="wp-muted">{{ __('portal.unassigned_qr.description') }}</p>
            </div>

            <form wire:submit="login" class="wp-stack">
                <div>
                    <label class="wp-label" for="email">{{ __('auth.email') }}</label>
                    <input 
                        id="email" 
                        type="email" 
                        class="wp-input" 
                        wire:model="email"
                        required
                        autocomplete="email"
                    >
                    @error('email')
                        <p class="wp-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="wp-label" for="password">{{ __('auth.password') }}</label>
                    <input 
                        id="password" 
                        type="password" 
                        class="wp-input" 
                        wire:model="password"
                        required
                        autocomplete="current-password"
                    >
                    @error('password')
                        <p class="wp-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn--primary">
                    {{ __('portal.unassigned_qr.login_button') }}
                </button>
            </form>
        </div>
    @else
        @if ($worker)
            <div class="wp-card wp-card-pad wp-cluster">
                @if ($worker->field_icon_slug)
                    <div class="wp-icon-tile is-selected" aria-hidden="true" style="pointer-events: none; width: 40px; height: 40px; padding: 0.35rem;">
                        <x-wp-worker-icon :slug="$worker->field_icon_slug" />
                    </div>
                @endif
                <strong class="wp-text-body">{{ __('common.welcome') }} {{ $worker->displayName() }}</strong>
            </div>
        @elseif (Auth::check())
            <div class="wp-card wp-card-pad wp-cluster">
                <strong class="wp-text-body">{{ __('common.welcome') }} {{ Auth::user()->name }}</strong>
            </div>
        @endif

        <form
            x-data
            x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
            @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.link()"
            class="wp-stack"
            wire:key="qr-binding-form"
        >
            <div class="wp-card wp-card-pad wp-stack">
                <h1 class="wp-section-title">{{ __('portal.unassigned_qr.title') }}</h1>

                <div class="wp-card-section">
                    <p class="wp-muted">{{ __('portal.unassigned_qr.sticker_number') }} : <code>{{ $qrCode->sticker_number }}</code></p>
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

                    @if ($units->isEmpty())
                        <p class="wp-muted">{{ __('qr.connect.no_units') }}</p>
                    @else
                        <div class="wp-list-plain wp-stack-tight">
                            @foreach ($units as $unit)
                                <label class="wp-list-row wp-list-row--interactive" wire:key="unit-option-{{ $unit->id }}">
                                    <input
                                        type="radio"
                                        name="unit"
                                        value="{{ $unit->id }}"
                                        wire:model="selectedUnitId"
                                    >
                                    <div class="wp-grow">
                                        <strong>{{ $unit->name }}</strong>
                                        @if ($unit->qrCodes && $unit->qrCodes->isNotEmpty())
                                            <span class="wp-muted wp-text-sm"> ({{ __('qr.connect.linked_qr') }} : {{ $unit->qrCodes->first()->sticker_number }})</span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        {{ $units->links() }}
                    @endif
                </div>

            </div>

            <div class="wp-card wp-card-pad wp-stack" wire:key="qr-binding-photos">
                <div class="wp-card-section">
                    <div class="wp-field">
                        <label class="wp-label">{{ __('portal.qr_binding.photos.label') }}</label>
                        @include('partials.wp-issue-photo-upload', ['model' => 'photos', 'preferCamera' => true, 'photoAlt' => __('portal.qr_binding.photos.add')])
                        @error('photos.*') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('photos') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="wp-card wp-card-pad">
                <div class="wp-card-actions">
                    <button
                        type="submit"
                        class="btn btn--primary"
                        :disabled="$selectedUnitId === null"
                    >
                        {{ __('qr.connect.link_button') }}
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
