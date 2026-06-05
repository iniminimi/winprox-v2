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

                <div class="wp-card-section">
                    <div class="wp-field">
                        <label class="wp-label">{{ __('portal.qr_binding.photos.label') }}</label>
                        @include('partials.wp-issue-photo-upload', ['model' => 'photos', 'preferCamera' => true, 'photoAlt' => __('portal.qr_binding.photos.add')])
                        @error('photos.*') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('photos') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-hint">{{ __('portal.qr_binding.photos.hint') }}</p>
                    </div>
                </div>

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
