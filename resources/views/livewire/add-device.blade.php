<div class="wp-dashboard-add-device">
    @if ($allowed && $open)
            <x-wp-modal closeMethod="closeModal" aria-labelledby="add-device-title">
                <div @class(['wp-card', 'wp-card-pad', 'wp-stack', 'wp-modal-card']) @if ($placeId !== null) wire:poll.5s @endif>
                    <div class="wp-modal-head">
                        <h2 id="add-device-title" class="wp-section-title">{{ __('dashboard.add_device.title') }}</h2>
                        <x-wp-modal-close wire:click="closeModal" />
                    </div>
                    <p class="wp-muted">{{ __('dashboard.add_device.intro') }}</p>

                    @if ($flash)
                        <div class="wp-flash wp-flash--success">{{ $flash }}</div>
                    @endif
                    @error('placeId') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('claim') <p class="wp-error">{{ $message }}</p> @enderror

                    <fieldset class="wp-stack-tight">
                        <legend class="wp-label">{{ __('dashboard.add_device.type_label') }}</legend>
                        <div class="wp-cluster wp-cluster--tight">
                            @foreach ($types as $type)
                                <label class="wp-check" wire:key="add-device-type-{{ $type }}">
                                    <input type="radio" name="add-device-type" value="{{ $type }}" wire:model.live="deviceType">
                                    <span>{{ __('dashboard.add_device.type_'.$type) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('deviceType') <p class="wp-error">{{ $message }}</p> @enderror
                    </fieldset>

                    @error('locationId') <p class="wp-error">{{ $message }}</p> @enderror

                    @if ($deviceType !== '')
                        <div class="wp-field">
                            <label class="wp-label" for="add-device-location">{{ __('dashboard.add_device.place_location') }}</label>
                            @if ($locations === [])
                                <p class="wp-muted">{{ __('dashboard.add_device.no_locations') }}</p>
                            @else
                                <select id="add-device-location" class="wp-select" wire:model.live="locationId">
                                    <option value="">{{ __('dashboard.add_device.place_placeholder') }}</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location['id'] }}">{{ $location['label'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        @if ($locationId !== null && $locations !== [])
                        <div class="wp-field">
                            <label class="wp-label" for="add-device-place">
                                {{ $deviceType === 'clock' ? __('dashboard.add_device.place_clock') : __('dashboard.add_device.place_unit') }}
                            </label>
                            @if ($places === [])
                                <p class="wp-muted">
                                    {{ $deviceType === 'clock' ? __('dashboard.add_device.no_clock_points') : __('dashboard.add_device.no_units') }}
                                </p>
                            @else
                                <select id="add-device-place" class="wp-select" wire:model.live="placeId">
                                    <option value="">{{ __('dashboard.add_device.place_placeholder') }}</option>
                                    @foreach ($places as $place)
                                        <option value="{{ $place['id'] }}">{{ $place['label'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        @if ($alreadyLinked)
                            <div class="wp-flash wp-flash--danger">{{ __('dashboard.add_device.replace_warning') }}</div>
                        @endif

                        @if ($places !== [])
                            <div class="wp-cluster">
                                <button type="button" class="btn btn--primary btn--sm" wire:click="issue" wire:loading.attr="disabled">
                                    {{ __('dashboard.add_device.issue') }}
                                </button>
                            </div>
                        @endif

                        @if ($code)
                            <p class="wp-display-code">{{ $code }}</p>
                            @if ($validUntil)
                                <p class="wp-muted">{{ __('dashboard.add_device.valid_until', ['time' => $validUntil]) }}</p>
                            @endif
                            <p class="wp-muted">{{ __('dashboard.add_device.code_hint') }}</p>
                        @endif

                        @if ($pending)
                            <div class="wp-stack-tight">
                                <p class="wp-section-title">{{ __('dashboard.add_device.pending_title') }}</p>
                                @if ($pending->device_hint)
                                    <p class="wp-issue-card-meta">{{ __('dashboard.add_device.pending_device', ['device' => $pending->device_hint]) }}</p>
                                @endif
                                <div class="wp-cluster">
                                    <button type="button" class="btn btn--primary btn--sm" wire:click="confirmClaim({{ $pending->id }})">
                                        {{ __('dashboard.add_device.confirm') }}
                                    </button>
                                    <button type="button" class="btn btn--surface btn--sm" wire:click="denyClaim({{ $pending->id }})">
                                        {{ __('dashboard.add_device.deny') }}
                                    </button>
                                </div>
                            </div>
                        @endif
                        @endif
                    @endif

                    <div class="wp-cluster">
                        <button type="button" class="btn btn--ghost" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    </div>
                </div>
            </x-wp-modal>
    @endif
</div>
