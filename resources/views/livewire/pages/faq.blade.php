<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('faq.title') }}</h1>
        <p class="wp-muted">{{ __('faq.subtitle') }}</p>
    </div>

    <div class="wp-faq">
        @foreach ($items as $item)
            @php $slug = $item['slug']; @endphp
            <div class="wp-faq-item {{ $openSlug === $slug ? 'is-open' : '' }}">
                <button type="button"
                        class="wp-faq-trigger"
                        wire:click="toggle('{{ $slug }}')"
                        aria-expanded="{{ $openSlug === $slug ? 'true' : 'false' }}">
                    <span>{{ $item['title'] }}</span>
                    <x-wp-icon name="document" class="wp-icon" />
                </button>
                @if ($openSlug === $slug)
                    <div class="wp-faq-body">
                        <p>{{ $item['body'] }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
