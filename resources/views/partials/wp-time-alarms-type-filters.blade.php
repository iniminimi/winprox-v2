@php
    use App\Enums\TimePresenceAttentionType;

    $types = [
        '' => __('time.alarms.type_all'),
        TimePresenceAttentionType::LongShift->value => __('time.alarms.type_long_shift'),
        TimePresenceAttentionType::StaleShift->value => __('time.alarms.type_stale_shift'),
        TimePresenceAttentionType::NoBreak->value => __('time.alarms.type_no_break'),
    ];
@endphp

<div class="wp-cluster wp-cluster--tight wp-time-presence-status-filters" role="tablist" aria-label="{{ __('time.alarms.type_filters') }}">
    @foreach ($types as $value => $label)
        @php
            $count = $value === '' ? $totalCount : (int) ($typeCounts[$value] ?? 0);
            $isActive = $value === ''
                ? $activeAttentionType === null
                : $activeAttentionType === TimePresenceAttentionType::from($value);
        @endphp
        <button type="button"
                role="tab"
                wire:click="setAttentionType('{{ $value }}')"
                @class([
                    'btn btn--sm',
                    $isActive ? 'btn--primary' : 'btn--surface',
                ])>
            {{ $label }}
            @if ($count > 0)
                <span class="wp-tabular">({{ $count }})</span>
            @endif
        </button>
    @endforeach
</div>
