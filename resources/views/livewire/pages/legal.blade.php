<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('legal.index_title') }}</h1>
        <p class="wp-muted">{{ __('legal.index_subtitle') }}</p>
    </div>

    <ul class="wp-legal-index">
        @foreach ($documents as $document)
            <li>
                <a href="{{ route($document['route']) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="wp-legal-index-link">
                    {{ __($document['label_key']) }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
