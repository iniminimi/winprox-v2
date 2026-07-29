<div class="wp-public-wrap wp-stack">
    <div class="wp-card wp-card-pad wp-stack">
        <h1 class="wp-welcome-h2">{{ __('reservations.public.manage_title') }}</h1>
        <p class="wp-muted">
            {{ $reservation->unit?->location?->name }} · {{ $reservation->unit?->name }}
        </p>
        <p>
            <span class="wp-pill wp-pill--{{ $reservation->lifecycle()->pillVariant() }}">
                {{ __('reservations.lifecycle.'.$reservation->lifecycle()->value) }}
            </span>
        </p>

        @if ($flashMessage !== '')
            <p class="wp-flash">{{ $flashMessage }}</p>
        @endif

        @if ($reservation->isEditable())
            <form wire:submit="save" class="wp-stack">
                <label class="wp-field">
                    <span>{{ __('reservations.fields.first_name') }}</span>
                    <input type="text" class="wp-input" wire:model="guestFirstName">
                    @error('guest_first_name') <p class="wp-error">{{ $message }}</p> @enderror
                </label>
                <label class="wp-field">
                    <span>{{ __('reservations.fields.last_name') }}</span>
                    <input type="text" class="wp-input" wire:model="guestLastName">
                    @error('guest_last_name') <p class="wp-error">{{ $message }}</p> @enderror
                </label>
                <label class="wp-field">
                    <span>{{ __('reservations.fields.email') }}</span>
                    <input type="email" class="wp-input" wire:model="guestEmail">
                    @error('guest_email') <p class="wp-error">{{ $message }}</p> @enderror
                </label>
                <label class="wp-field">
                    <span>{{ __('reservations.fields.start_at') }}</span>
                    <x-wp-half-hour-datetime wire:model="startAt" />
                    @error('start_at') <p class="wp-error">{{ $message }}</p> @enderror
                </label>
                <label class="wp-field">
                    <span>{{ __('reservations.fields.end_at') }}</span>
                    <x-wp-half-hour-datetime wire:model="endAt" />
                    @error('end_at') <p class="wp-error">{{ $message }}</p> @enderror
                </label>
                <div class="wp-cluster">
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                    <button type="button" class="btn btn--danger" wire:click="cancel" wire:confirm="{{ __('reservations.public.cancel_confirm') }}">
                        {{ __('reservations.actions.cancel') }}
                    </button>
                </div>
            </form>
        @elseif ($reservation->isCancellable())
            <button type="button" class="btn btn--danger" wire:click="cancel" wire:confirm="{{ __('reservations.public.cancel_confirm') }}">
                {{ __('reservations.actions.cancel') }}
            </button>
        @endif
    </div>
</div>
