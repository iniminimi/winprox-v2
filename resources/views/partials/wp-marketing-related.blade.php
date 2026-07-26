@props([
    'links' => [],
    'title' => null,
])

@if ($links !== [])
    <aside class="wp-welcome-related wp-card wp-card-pad" aria-labelledby="marketing-related-title">
        <h2 id="marketing-related-title" class="wp-welcome-h3">{{ $title ?? __('features.shared.related_title') }}</h2>
        <ul class="wp-welcome-related__list">
            @foreach ($links as $link)
                <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
            @endforeach
        </ul>
    </aside>
@endif
