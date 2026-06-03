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
        <div class="wp-card wp-card-pad wp-stack">
            <h1 class="wp-section-title">{{ __('portal.unassigned_qr.title') }}</h1>
            
            <div class="wp-card-section">
                <dl class="wp-key-value">
                    <dt>{{ __('portal.unassigned_qr.sticker_number') }}</dt>
                    <dd><code>{{ $qrCode->sticker_number }}</code></dd>
                </dl>
            </div>

            <div class="wp-card-section">
                <h3>{{ __('qr.connect.select_unit') }}</h3>
                
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

                    {{ $units->links() }}
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
