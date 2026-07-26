<div class="wp-stack" data-manual-capture="reservations">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="calendar"
                :title="__('reservations.title')"
                :subtitle="__('reservations.subtitle')"
            />
        </div>
        @can('create', App\Models\Reservation::class)
            <button type="button" class="btn btn--primary btn--sm" wire:click="openCreate">
                {{ __('reservations.actions.create') }}
            </button>
        @endcan
    </div>

    <div class="wp-card wp-card-pad">
        <div class="wp-table-wrap">
            <table class="wp-table">
                <thead>
                    <tr>
                        <th>{{ __('reservations.columns.when') }}</th>
                        <th>{{ __('reservations.columns.unit') }}</th>
                        <th>{{ __('reservations.columns.guest') }}</th>
                        <th>{{ __('reservations.columns.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr wire:key="res-{{ $reservation->id }}">
                            <td>
                                {{ $reservation->start_at?->format('d-m-Y H:i') }}
                                –
                                {{ $reservation->end_at?->format('H:i') }}
                            </td>
                            <td>
                                {{ $reservation->unit?->location?->name }}
                                ·
                                {{ $reservation->unit?->name }}
                            </td>
                            <td>
                                {{ $reservation->guestFullName() }}
                                <div class="wp-muted">{{ $reservation->guest_email }}</div>
                            </td>
                            <td>
                                <span class="wp-pill wp-pill--{{ $reservation->lifecycle()->pillVariant() }}">
                                    {{ __('reservations.lifecycle.'.$reservation->lifecycle()->value) }}
                                </span>
                            </td>
                            <td class="wp-cluster">
                                @can('update', $reservation)
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openEdit({{ $reservation->id }})">
                                        {{ __('common.button.edit') }}
                                    </button>
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
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="wp-muted">{{ __('reservations.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="wp-pagination">{{ $reservations->links() }}</div>
    </div>

    @if ($showForm)
        <x-wp-modal closeMethod="closeForm">
            <x-slot:title>
                {{ $editingId ? __('reservations.actions.edit') : __('reservations.actions.create') }}
            </x-slot:title>
            <form wire:submit="save" class="wp-stack">
                @if ($editingId === null)
                    <label class="wp-field">
                        <span>{{ __('reservations.fields.unit') }}</span>
                        <select class="wp-select" wire:model="unitId">
                            <option value="">{{ __('reservations.fields.unit_placeholder') }}</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->location?->name }} · {{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('unit_id') <p class="wp-error">{{ $message }}</p> @enderror
                    </label>
                @endif
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
                    <input type="datetime-local" class="wp-input" wire:model="startAt">
                    @error('start_at') <p class="wp-error">{{ $message }}</p> @enderror
                </label>
                <label class="wp-field">
                    <span>{{ __('reservations.fields.end_at') }}</span>
                    <input type="datetime-local" class="wp-input" wire:model="endAt">
                    @error('end_at') <p class="wp-error">{{ $message }}</p> @enderror
                </label>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeForm">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
