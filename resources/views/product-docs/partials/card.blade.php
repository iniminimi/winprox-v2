<section class="wp-product-doc-card">
    <h2 class="wp-product-doc-card__title">
        {{ $card['title'] ?? '' }}
        @if (! empty($card['badge']))
            <span @class([
                'wp-product-doc-badge',
                'wp-product-doc-badge--'.($card['badge_mod'] ?? '') => filled($card['badge_mod'] ?? null),
            ])>{{ $card['badge'] }}</span>
        @endif
    </h2>
    @if (! empty($card['body']))
        <p class="wp-product-doc-card__body">{{ $card['body'] }}</p>
    @endif
    @if (! empty($card['items']) && is_array($card['items']))
        <ul class="wp-product-doc-card__list">
            @foreach ($card['items'] as $item)
                <li>{!! preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e((string) $item)) !!}</li>
            @endforeach
        </ul>
    @endif
</section>
