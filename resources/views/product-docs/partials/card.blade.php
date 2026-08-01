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
        <p class="wp-product-doc-card__body">{!! preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e((string) $card['body'])) !!}</p>
    @endif
    @if (! empty($card['items']) && is_array($card['items']))
        <ul class="wp-product-doc-card__list">
            @foreach ($card['items'] as $item)
                <li>{!! preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e((string) $item)) !!}</li>
            @endforeach
        </ul>
    @endif
    @if (! empty($card['table']) && is_array($card['table']))
        @php
            $headers = $card['table']['headers'] ?? [];
            $rows = $card['table']['rows'] ?? [];
        @endphp
        @if (is_array($headers) && is_array($rows) && $headers !== [])
            <div class="wp-product-doc-table-wrap">
                <table class="wp-product-doc-table">
                    <thead>
                        <tr>
                            @foreach ($headers as $header)
                                <th scope="col">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @if (is_array($row))
                                <tr>
                                    @foreach ($row as $cell)
                                        <td>{!! preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e((string) $cell)) !!}</td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
    @if (! empty($card['emphasis']))
        <p class="wp-product-doc-card__emphasis">{!! preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e((string) $card['emphasis'])) !!}</p>
    @endif
</section>
