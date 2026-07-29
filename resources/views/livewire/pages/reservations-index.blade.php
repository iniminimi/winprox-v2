<div class="wp-stack" data-manual-capture="reservations">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="calendar"
                :title="__('reservations.title')"
                help-page="reservations"
                :subtitle="__('reservations.subtitle')"
            />
        </div>
        <div class="wp-cluster">
            <a href="{{ $calendarReservationsUrl }}" class="btn btn--ghost btn--sm">{{ __('reservations.actions.open_calendar') }}</a>
            @can('create', App\Models\Reservation::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreate" @disabled($reservableUnitCount === 0)>
                    {{ __('reservations.actions.create') }}
                </button>
            @endcan
        </div>
    </div>

    @if (session('reservations_flash'))
        <div class="wp-flash wp-flash--success">{{ session('reservations_flash') }}</div>
    @endif

    @if ($reservableUnitCount === 0)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <p class="wp-section-title">{{ __('reservations.setup.title') }}</p>
            <p class="wp-muted">{{ __('reservations.setup.lead') }}</p>
            <ol class="wp-list-plain wp-text-sm wp-muted wp-stack-tight">
                @foreach (__('reservations.setup.steps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
            <div class="wp-cluster">
                <a href="{{ $locationsUrl }}" class="btn btn--primary btn--sm">{{ __('reservations.setup.cta_locations') }}</a>
            </div>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster wp-cluster--between wp-cluster--wrap">
            <p class="wp-section-title">{{ __('reservations.list_title') }}</p>
            <div class="wp-cluster wp-cluster--wrap">
                <select class="wp-select wp-select--compact" wire:model.live="statusFilter" aria-label="{{ __('reservations.filters.status') }}">
                    <option value="upcoming">{{ __('reservations.filters.upcoming') }}</option>
                    <option value="pending">{{ __('reservations.filters.pending') }}</option>
                    <option value="confirmed">{{ __('reservations.filters.confirmed') }}</option>
                    <option value="past">{{ __('reservations.filters.past') }}</option>
                    <option value="all">{{ __('reservations.filters.all') }}</option>
                </select>
                <select class="wp-select wp-select--compact" wire:model.live="locationFilter" aria-label="{{ __('reservations.filters.location') }}">
                    <option value="">{{ __('reservations.filters.all_locations') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="wp-list">
            @forelse ($reservations as $reservation)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="res-{{ $reservation->id }}">
                    <div class="wp-stack-tight">
                        <div class="wp-cluster wp-cluster--wrap">
                            <strong>{{ $reservation->start_at?->format('d-m-Y H:i') }} – {{ $reservation->end_at?->format('H:i') }}</strong>
                            <span class="wp-pill wp-pill--{{ $reservation->lifecycle()->pillVariant() }}">
                                {{ __('reservations.lifecycle.'.$reservation->lifecycle()->value) }}
                            </span>
                        </div>
                        <p class="wp-text-body">
                            {{ $reservation->unit?->location?->name }}
                            ·
                            {{ $reservation->unit?->name }}
                        </p>
                        <p class="wp-muted wp-text-sm">
                            {{ $reservation->guestFullName() }}
                            ·
                            {{ $reservation->guest_email }}
                        </p>
                    </div>
                    <div class="wp-cluster">
                        @can('update', $reservation)
                            @if ($reservation->isPendingActive())
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="resendConfirmMail({{ $reservation->id }})">
                                    {{ __('reservations.actions.resend_confirm') }}
                                </button>
                            @endif
                            @if ($reservation->isEditable())
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openEdit({{ $reservation->id }})">
                                    {{ __('common.button.edit') }}
                                </button>
                            @endif
                        @endcan
                        @can('delete', $reservation)
                            @if ($reservation->isCancellable())
                                <button type="button" class="btn btn--danger btn--sm"
                                    wire:confirm="{{ __('reservations.public.cancel_confirm') }}"
                                    wire:click="cancelReservation({{ $reservation->id }})">
                                    {{ __('reservations.actions.cancel') }}
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="wp-stack-tight">
                    <p class="wp-section-title">{{ __('reservations.empty_title') }}</p>
                    <p class="wp-muted">{{ __('reservations.empty_body') }}</p>
                    @if ($reservableUnitCount > 0)
                        @can('create', App\Models\Reservation::class)
                            <button type="button" class="btn btn--primary btn--sm" wire:click="openCreate">
                                {{ __('reservations.actions.create') }}
                            </button>
                        @endcan
                    @endif
                </div>
            @endforelse
        </div>

        @if ($reservations->hasPages())
            <div class="wp-pagination">{{ $reservations->links() }}</div>
        @endif
    </div>

    @if ($showForm)
        <x-wp-modal closeMethod="closeForm" aria-labelledby="reservation-form-title">
            <form wire:submit="save" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="reservation-form-title" class="wp-section-title">
                        {{ $editingId ? __('reservations.actions.edit') : __('reservations.actions.create') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeForm" />
                </div>
                <div class="wp-modal-body wp-stack">
                    @if ($editingId === null)
                        <label class="wp-field">
                            <span class="wp-label">{{ __('reservations.fields.unit') }}</span>
                            <select class="wp-select" wire:model="unitId">
                                <option value="">{{ __('reservations.fields.unit_placeholder') }}</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->location?->name }} · {{ $unit->name }}</option>
                                @endforeach
                            </select>
                            @error('unit_id') <p class="wp-error">{{ $message }}</p> @enderror
                        </label>
                        <p class="wp-hint">{{ __('reservations.form.staff_hint') }}</p>
                    @endif
                    <div class="wp-form-grid-2">
                        <label class="wp-field">
                            <span class="wp-label">{{ __('reservations.fields.first_name') }}</span>
                            <input type="text" class="wp-input" wire:model="guestFirstName">
                            @error('guest_first_name') <p class="wp-error">{{ $message }}</p> @enderror
                        </label>
                        <label class="wp-field">
                            <span class="wp-label">{{ __('reservations.fields.last_name') }}</span>
                            <input type="text" class="wp-input" wire:model="guestLastName">
                            @error('guest_last_name') <p class="wp-error">{{ $message }}</p> @enderror
                        </label>
                    </div>
                    <label class="wp-field">
                        <span class="wp-label">{{ __('reservations.fields.email') }}</span>
                        <input type="email" class="wp-input" wire:model="guestEmail">
                        @error('guest_email') <p class="wp-error">{{ $message }}</p> @enderror
                    </label>
                    <div class="wp-form-grid-2">
                        <label class="wp-field">
                            <span class="wp-label">{{ __('reservations.fields.start_at') }}</span>
                            <input type="datetime-local" class="wp-input" wire:model="startAt" step="1800">
                            @error('start_at') <p class="wp-error">{{ $message }}</p> @enderror
                        </label>
                        <label class="wp-field">
                            <span class="wp-label">{{ __('reservations.fields.end_at') }}</span>
                            <input type="datetime-local" class="wp-input" wire:model="endAt" step="1800">
                            @error('end_at') <p class="wp-error">{{ $message }}</p> @enderror
                        </label>
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeForm">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading wire:target="save" class="wp-mr-2">
                            <x-wp-spinner size="sm" />
                        </span>
                        <span>{{ __('common.button.save') }}</span>
                    </button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
