<div class="wp-stack">
    <x-wp-page-head-title
        :assistant-video="asset('video/assistant_legal_80.mp4')"
        assistant-video-loop
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
