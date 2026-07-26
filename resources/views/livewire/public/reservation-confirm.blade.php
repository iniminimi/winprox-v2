<div class="wp-public-wrap wp-stack">
    <div class="wp-card wp-card-pad wp-stack">
        <h1 class="wp-welcome-h2">{{ __('reservations.public.confirm_title') }}</h1>
        <p class="{{ $status === 'ok' ? 'wp-text-body' : 'wp-error' }}">{{ $message }}</p>
        @if ($reservation)
            <p class="wp-muted">
                {{ $reservation->unit?->location?->name }} · {{ $reservation->unit?->name }}
            </p>
            <p class="wp-text-body">
                {{ $reservation->start_at?->format('d-m-Y H:i') }} – {{ $reservation->end_at?->format('H:i') }}
            </p>
            @if ($reservation->isConfirmed())
                <a href="{{ route('reservations.manage', ['token' => $reservation->manage_token]) }}" class="btn btn--primary">
                    {{ __('reservations.public.open_manage') }}
                </a>
            @endif
        @endif
    </div>
</div>
