@php
    use Carbon\CarbonImmutable;

    $groupedChecks = $checks
        ->groupBy(fn ($check) => (string) $check->checked_at?->toDateString())
        ->map(function ($group, string $dayKey): array {
            $date = CarbonImmutable::parse($dayKey);

            return [
                'key' => $dayKey,
                'label' => $date->translatedFormat('l d-m-Y'),
                'checks' => $group->values(),
            ];
        })
        ->values();
@endphp

<x-wp-report-print
    :title="__('reports.unit_checks.title')"
    :document-title="__('reports.unit_checks.document_title')"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$checks->count()"
>
    @forelse ($groupedChecks as $group)
        <section class="wp-status-block wp-report-print__block">
            <div class="wp-group-head wp-group-head--new">
                <h2 class="wp-group-title">{{ $group['label'] }}</h2>
                <span class="wp-group-count">{{ $group['checks']->count() }}</span>
            </div>
            <div class="wp-stack-tight">
                @foreach ($group['checks'] as $check)
                    <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread wp-report-print__card">
                        <div class="wp-stack-tight">
                            <div class="wp-cluster wp-cluster--wrap">
                                <strong>{{ $check->checked_at?->format('H:i') }}</strong>
                                <span class="wp-pill wp-pill--{{ $check->result->pillVariant() }}">
                                    {{ __('unit_checks.result.'.$check->result->value) }}
                                </span>
                            </div>
                            <p class="wp-text-body">
                                {{ $check->location?->name }}
                                ·
                                {{ $check->unit?->name }}
                            </p>
                            <p class="wp-muted wp-text-sm">
                                {{ $check->worker?->displayName() ?? __('unit_checks.worker_unknown') }}
                                @if ($check->team)
                                    · {{ $check->team->localizedName() }}
                                @endif
                                @if (is_array($check->checklist_items) && $check->checklist_items !== [])
                                    · {{ implode(', ', $check->checklist_items) }}
                                @endif
                            </p>
                        </div>
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
