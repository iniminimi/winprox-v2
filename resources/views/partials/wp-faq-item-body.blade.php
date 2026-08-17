{{-- Body van één FAQ-item (altijd in de DOM — geschikt voor welcome/SEO/bots). --}}
@php
    $type = $item['type'] ?? 'text';
@endphp

@if (! empty($item['intro']) && in_array($type, ['steps', 'pricing', 'portal', 'roles'], true))
    <p class="wp-text-body">{{ $item['intro'] }}</p>
@endif

@switch($type)
    @case('steps')
        <div class="wp-stack">
            @foreach ($item['steps'] ?? [] as $index => $step)
                <div class="wp-stack-tight">
                    <p class="wp-subhead">{{ $index + 1 }}. {{ $step['title'] ?? '' }}</p>
                    <p class="wp-muted">{{ $step['text'] ?? '' }}</p>
                    @if (! empty($step['highlight']))
                        <p class="wp-flash">{{ $step['highlight'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if (! empty($item['box_title']) || ! empty($item['box_body']))
            <div class="wp-flash wp-stack-tight">
                @if (! empty($item['box_title']))
                    <p class="wp-subhead">{{ $item['box_title'] }}</p>
                @endif
                @if (! empty($item['box_body']))
                    <p class="wp-text-body">{{ $item['box_body'] }}</p>
                @endif
            </div>
        @endif
        @break

    @case('pricing')
        <div class="wp-stack">
            @foreach (\App\Support\Billing\BillingCatalogViewData::publicPlanKeys() as $planKey)
                @php $plan = $item['plans'][$planKey] ?? null; @endphp
                @if ($plan)
                    <article class="wp-stack-tight">
                        <h4 class="wp-section-title">{{ __("subscription.plans.{$planKey}.name") }}</h4>
                        <ul class="wp-plan-limits">
                            <li>{{ $plan['units'] ?? '' }}</li>
                            <li>{{ $plan['users'] ?? '' }}</li>
                            @if (! empty($plan['announcements']))
                                <li>{{ $plan['announcements'] }}</li>
                            @endif
                            @if (! empty($plan['documents']))
                                <li>{{ $plan['documents'] }}</li>
                            @endif
                        </ul>
                        <p class="wp-muted">{{ $plan['description'] ?? '' }}</p>
                    </article>
                @endif
            @endforeach
        </div>
        @if (! empty($item['content_note']))
            <p class="wp-muted">{{ $item['content_note'] }}</p>
        @endif
        @break

    @case('roles')
    @case('portal')
        <div class="wp-stack">
            @foreach (($type === 'roles' ? ($item['roles'] ?? []) : ($item['sections'] ?? [])) as $block)
                <div class="wp-stack-tight">
                    <p class="wp-subhead">{{ $block['title'] ?? '' }}</p>
                    <p class="wp-muted">{{ $block['text'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
        @break

    @default
        @if (! empty($item['body']))
            <p class="wp-text-body">{{ $item['body'] }}</p>
        @elseif (! empty($item['summary']))
            <p class="wp-text-body">{{ $item['summary'] }}</p>
        @endif
@endswitch
