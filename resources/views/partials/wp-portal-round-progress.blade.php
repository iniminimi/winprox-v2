{{-- Rijke voortgang voor inspectieronde (fase 2). --}}
@props(['progress', 'currentUnitId' => null])

@php
    $done = (int) ($progress['done'] ?? 0);
    $total = max(1, (int) ($progress['total'] ?? 1));
    $pct = (int) round(($done / $total) * 100);
@endphp

<div class="wp-round-progress wp-stack-tight">
    <div class="wp-row">
        <p class="wp-muted wp-text-sm wp-grow">
            {{ __('portal.round.progress', ['done' => $done, 'total' => $progress['total']]) }}
        </p>
        @if (! empty($progress['next_unit_name']) && (int) ($progress['open'] ?? 0) > 0)
            <p class="wp-text-sm">{{ __('portal.round.next_stop', ['name' => $progress['next_unit_name']]) }}</p>
        @endif
    </div>

    <div class="wp-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"
         aria-label="{{ __('portal.round.progress', ['done' => $done, 'total' => $progress['total']]) }}">
        <div class="wp-progress__bar" style="width: {{ $pct }}%"></div>
    </div>

    <ol class="wp-round-stops">
        @foreach ($progress['stops'] as $index => $stop)
            @php
                $state = $stop['state'];
                $pill = match ($state) {
                    'ok' => 'done',
                    'not_ok' => 'closed',
                    'skipped' => 'closed',
                    'current' => 'progress',
                    default => 'new',
                };
                $label = match ($state) {
                    'ok' => __('portal.round.stop_ok'),
                    'not_ok' => __('portal.round.stop_not_ok'),
                    'skipped' => __('portal.round.stop_skipped'),
                    'current' => __('portal.round.stop_current'),
                    default => __('portal.round.stop_open'),
                };
                $isHere = $currentUnitId !== null && (int) $stop['unit_id'] === (int) $currentUnitId;
            @endphp
            <li @class(['wp-round-stops__item', 'wp-round-stops__item--here' => $isHere])>
                <span class="wp-round-stops__index">{{ $index + 1 }}</span>
                <span class="wp-round-stops__name">{{ $stop['name'] }}</span>
                <span class="wp-pill wp-pill--xs wp-pill--{{ $pill }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</div>
