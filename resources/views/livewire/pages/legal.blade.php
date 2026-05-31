<div class="wp-stack">
    <x-wp-page-head-title
        icon="legal"
        :title="__('legal.index_title')"
        help-page="legal"
        :subtitle="__('legal.index_subtitle')"
    />

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
