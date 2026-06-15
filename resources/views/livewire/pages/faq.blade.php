<div class="wp-stack">
    <div class="wp-faq-wrap">
        <x-wp-page-head-title
            icon="faq"
            :title="__('faq.title')"
            help-page="faq"
            :subtitle="__('faq.subtitle')"
        />

        <div class="wp-stack">
            @foreach ($items as $item)
                @php
                    $slug = $item['slug'];
                    $type = $item['type'] ?? 'text';
                    $headerIntro = $item['intro'] ?? null;
                    $showIntroInHeader = ! empty($headerIntro) && $openSlug !== $slug;
                @endphp
                <div class="wp-card wp-faq-item {{ $openSlug === $slug ? 'is-open' : '' }}" wire:key="faq-{{ $slug }}">
                    <button type="button"
                            class="wp-faq-trigger"
                            wire:click="toggle('{{ $slug }}')"
                            aria-expanded="{{ $openSlug === $slug ? 'true' : 'false' }}">
                        <div class="wp-grow wp-stack-tight">
                            <p class="wp-subhead">{{ $item['title'] }}</p>
                            @if ($showIntroInHeader)
                                <p class="wp-muted">{{ $headerIntro }}</p>
                            @endif
                        </div>
                        <span class="wp-faq-icon" aria-hidden="true">{{ $openSlug === $slug ? '−' : '+' }}</span>
                    </button>

                    @if ($openSlug === $slug)
                        <div class="wp-faq-panel wp-card-pad wp-stack">
                            @if (! empty($item['intro']) && in_array($type, ['steps', 'pricing', 'portal', 'roles'], true))
                                <p class="wp-text-body">{{ $item['intro'] }}</p>
                            @endif

                            @switch($type)
                                @case('steps')
                                    <div class="wp-stack">
                                        @foreach ($item['steps'] ?? [] as $index => $step)
                                            <div class="wp-card wp-card-pad wp-stack-tight">
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
                                        @foreach (array_keys(config('billing.plans', [])) as $planKey)
                                            @php $plan = $item['plans'][$planKey] ?? null; @endphp
                                            @if ($plan)
                                                <article class="wp-card wp-card-pad wp-stack-tight">
                                                    <h3 class="wp-section-title">{{ __("subscription.plans.{$planKey}.name") }}</h3>
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
                                            <div class="wp-card wp-card-pad wp-stack-tight">
                                                <p class="wp-subhead">{{ $block['title'] ?? '' }}</p>
                                                <p class="wp-muted">{{ $block['text'] ?? '' }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                    @break

                                @default
                                    @if (! empty($item['body']))
                                        <p class="wp-text-body">{{ $item['body'] }}</p>
                                    @endif
                            @endswitch
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
