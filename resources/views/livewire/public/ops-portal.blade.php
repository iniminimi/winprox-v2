<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx" style="max-width: 100px; max-height: 100px; object-fit: contain;">
            </span>
            <div class="wp-cluster wp-cluster--tight">
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <p class="wp-section-title">{{ __('ops.portal.title') }}</p>
        <p class="wp-muted">{{ __('ops.portal.subtitle') }}</p>
    </div>

    @if ($flashMessage)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $flashType !== 'error',
            'wp-flash--danger' => $flashType === 'error',
        ])>{{ $flashMessage }}</div>
    @endif

    @unless ($authed)
        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('ops.portal.login_title') }}</p>
            <p class="wp-muted wp-text-sm">{{ __('ops.portal.login_hint') }}</p>
            <form wire:submit="login" class="wp-stack">
                <div>
                    <label class="wp-label" for="ops-login-email">{{ __('auth.email') }}</label>
                    <input id="ops-login-email" type="email" class="wp-input" wire:model="email" autocomplete="username">
                    @error('email') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="wp-label" for="ops-login-password">{{ __('auth.password') }}</label>
                    <input id="ops-login-password" type="password" class="wp-input" wire:model="password" autocomplete="current-password">
                    @error('password') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">{{ __('ops.portal.login_submit') }}</button>
            </form>
        </div>
    @else
        <div class="wp-cluster wp-cluster--wrap">
            <button type="button" @class(['btn', 'btn--primary' => $mode === 'register', 'btn--ghost' => $mode !== 'register']) wire:click="setMode('register')">
                {{ __('ops.portal.tab_register') }}
            </button>
            <button type="button" @class(['btn', 'btn--primary' => $mode === 'mac', 'btn--ghost' => $mode !== 'mac']) wire:click="setMode('mac')">
                {{ __('ops.portal.tab_mac') }}
            </button>
            <button type="button" class="btn btn--ghost" wire:click="logout">{{ __('ops.portal.logout') }}</button>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            {{-- Alpine los van Livewire-morph --}}
            <div
                wire:ignore
                x-data="wpOpsBarcodeScanner({
                    i18n: {
                        photo_no_code: @js(__('ops.scanner.photo_no_code')),
                        photo_failed: @js(__('ops.scanner.photo_failed')),
                    }
                })"
            >
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="sr-only"
                    x-ref="photoCapture"
                    @change="onPhotoSelected($event)"
                >
                <button
                    type="button"
                    class="btn btn--primary"
                    @click="openPhotoCapture()"
                    :disabled="photoBusy"
                >
                    <span x-show="!photoBusy">{{ __('ops.scanner.photo_capture') }}</span>
                    <span x-show="photoBusy" x-cloak>{{ __('ops.scanner.photo_busy') }}</span>
                </button>
                <p class="wp-muted wp-text-sm">{{ __('ops.scanner.photo_hint') }}</p>
                <p class="wp-error" x-show="photoErrorText" x-text="photoErrorText" x-cloak></p>
            </div>

            @if ($mode === 'register')
                <form wire:submit="register" class="wp-stack">
                    <div>
                        <label class="wp-label" for="ops-sn">{{ __('ops.fields.serial_number') }}</label>
                        <input id="ops-sn" type="text" class="wp-input" wire:model="serial_number" autocomplete="off">
                        @error('serial_number') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-brand">{{ __('ops.fields.brand') }}</label>
                            <input id="ops-brand" type="text" class="wp-input" wire:model="brand" autocomplete="off">
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-model">{{ __('ops.fields.model') }}</label>
                            <input id="ops-model" type="text" class="wp-input" wire:model="model" autocomplete="off">
                        </div>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-type">{{ __('ops.fields.type') }}</label>
                            <select id="ops-type" class="wp-input" wire:model="registerType">
                                @foreach ($types as $typeCase)
                                    <option value="{{ $typeCase->value }}">{{ __($typeCase->labelKey()) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-sku">{{ __('ops.fields.sku') }}</label>
                            <input id="ops-sku" type="text" class="wp-input" wire:model="sku" autocomplete="off"
                                   placeholder="{{ __('ops.fields.sku_optional') }}">
                        </div>
                    </div>
                    <div>
                        <label class="wp-label" for="ops-condition">{{ __('ops.fields.condition') }}</label>
                        <select id="ops-condition" class="wp-input" wire:model="condition">
                            @foreach ($conditions as $conditionCase)
                                <option value="{{ $conditionCase->value }}">{{ __($conditionCase->labelKey()) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="wp-label" for="ops-purchase">{{ __('ops.fields.purchase_ref') }}</label>
                        <input id="ops-purchase" type="text" class="wp-input" wire:model="purchase_ref" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn--primary">{{ __('ops.devices.register_submit') }}</button>
                </form>
            @else
                <form wire:submit="bindMac" class="wp-stack">
                    <div>
                        <label class="wp-label" for="ops-mac-sn">{{ __('ops.fields.serial_number') }}</label>
                        <input id="ops-mac-sn" type="text" class="wp-input" wire:model="macSerial" autocomplete="off">
                        @error('macSerial') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wp-label" for="ops-mac">{{ __('ops.fields.mac') }}</label>
                        <input id="ops-mac" type="text" class="wp-input" wire:model="mac" autocomplete="off"
                               placeholder="{{ __('ops.fields.mac_placeholder') }}">
                        <p class="wp-muted wp-text-sm">{{ __('ops.devices.mac_hint') }}</p>
                        @error('mac') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn btn--primary">{{ __('ops.portal.mac_submit') }}</button>
                </form>
            @endif
        </div>
    @endunless
</div>
