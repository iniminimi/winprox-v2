<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — WinProx</title>
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-legal-doc">
    <article class="wp-legal">
        <header class="wp-legal-head">
            <h1 class="wp-legal-title">{{ $title }}</h1>
            <p class="wp-legal-meta">
                {{ __('legal.updated', ['date' => $meta['updated'] ?? '']) }}
                &middot;
                {{ config('legal.operator') }}
                &middot;
                {{ config('legal.jurisdiction') }}
            </p>
            <nav class="wp-legal-nav" aria-label="{{ __('legal.nav_label') }}">
                @foreach (config('legal.documents', []) as $key => $document)
                    <a href="{{ route($document['route']) }}"
                       class="wp-legal-nav-link {{ $key === $doc ? 'is-active' : '' }}">
                        {{ __($document['label_key']) }}
                    </a>
                @endforeach
            </nav>
        </header>

        <div class="wp-legal-body">
            @includeFirst([
                "legal.content.{$locale}.{$doc}",
                "legal.content.en.{$doc}",
            ])
        </div>
    </article>
</body>
</html>
