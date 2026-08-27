<x-wp-report-print
    :title="__('reports.reservations.title')"
    :document-title="__('reports.reservations.document_title')"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$reservations->count()"
>
    @if ($reservations->isEmpty())
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('reports.empty') }}</p>
        </div>
    @else
        <div class="wp-list wp-stack">
            @foreach ($reservations as $reservation)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread wp-report-print__card">
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
                </div>
            @endforeach
        </div>
    @endif
</x-wp-report-print>
