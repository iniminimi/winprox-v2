@php
    $tz = config('app.timezone');
    $groups = $submissions
        ->groupBy(function ($submission) use ($tz) {
            $day = $submission->registration_at?->timezone($tz)->format('Y-m-d') ?? 'unknown';

            return ((string) ($submission->worker_id ?? '0')).'|'.$day;
        })
        ->values();
@endphp

<x-wp-report-print
    :title="__('reports.ciao.title')"
    :document-title="__('reports.ciao.document_title')"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$submissions->count()"
>
    @forelse ($groups as $groupItems)
        @php
            $first = $groupItems->first();
            $dayLabel = $first->registration_at?->timezone($tz)->format('d-m-Y') ?? '—';
        @endphp
        <section class="wp-status-block wp-report-print__block">
            <div class="wp-group-head wp-group-head--new">
                <h2 class="wp-group-title">
                    {{ $first->worker?->displayName() ?? '—' }}
                    · {{ $dayLabel }}
                </h2>
                <span class="wp-group-count">{{ $groupItems->count() }}</span>
            </div>
            <div class="wp-stack-tight">
                @foreach ($groupItems as $submission)
                    @php
                        $detail = null;
                        if ($submission->rsz_id) {
                            $detail = 'RSZ #'.$submission->rsz_id;
                            if ($submission->rsz_validity) {
                                $detail .= ' · '.$submission->rsz_validity;
                            }
                        } elseif ($submission->error_message) {
                            $detail = explode(':', (string) $submission->error_message, 2)[0];
                        }
                    @endphp
                    <div class="wp-card wp-card-pad wp-report-print__card">
                        <p class="wp-text-body">
                            {{ $submission->registration_at?->timezone($tz)->format('H:i') }}
                            {{ __('time.ciao.event.'.$submission->source_event->value) }}
                            · {{ $submission->presence_type->value }}
                            @if ($submission->location)
                                · {{ $submission->location->name }}
                            @endif
                            @if ($detail)
                                , {{ $detail }}
                            @endif
                            · {{ __('time.ciao.status.'.$submission->status->value) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('reports.empty') }}</p>
        </div>
    @endforelse
</x-wp-report-print>
