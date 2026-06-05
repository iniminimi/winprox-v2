<div class="wp-stack">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <x-wp-page-head-title
            icon="api"
            :title="__('settings.api.docs_title')"
            :subtitle="__('settings.api.docs_subtitle')"
        />
        
        <button wire:click="download" class="btn btn--ghost btn--sm">
            {{ __('settings.api.download_docs') }}
        </button>
    </div>

    <div class="wp-card wp-card-pad">
        <nav class="wp-doc-nav">
            @foreach ($sidebar as $key => $label)
                <a href="{{ route('settings.api.docs.show', $key) }}"
                   class="wp-doc-nav-link {{ $current === $key ? 'wp-doc-nav-link--active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="wp-doc-body">
            {!! $content !!}
        </div>
    </div>
</div>
