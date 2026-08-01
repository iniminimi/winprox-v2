<div class="wp-stack">
    <div class="wp-faq-wrap">
        <x-wp-page-head-title
            :assistant-video="asset('video/assistant_question.mp4')"
            :title="__('faq.title')"
            help-page="faq"
            :subtitle="__('faq.subtitle')"
        />

        <div class="wp-stack">
            @foreach ($items as $item)
                @php
                    $slug = $item['slug'];
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
                            @include('partials.wp-faq-item-body', ['item' => $item])
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
