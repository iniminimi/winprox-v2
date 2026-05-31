<div class="wp-auth-legal">
    <p class="wp-auth-legal-title">{{ __('legal.inline_title') }}</p>
    <div class="wp-auth-legal-links">
        @foreach (config('legal.documents', []) as $legalMeta)
            @if (! $loop->first)
                <span class="wp-auth-legal-sep" aria-hidden="true">&middot;</span>
            @endif
            <a href="{{ route($legalMeta['route']) }}" class="wp-auth-legal-link" target="_blank" rel="noopener noreferrer">
                {{ __($legalMeta['label_key']) }}
            </a>
        @endforeach
        <span class="wp-auth-legal-sep" aria-hidden="true">&middot;</span>
        <a href="{{ route('contact.index') }}" class="wp-auth-legal-link">{{ __('common.nav.contact') }}</a>
    </div>
    <p class="wp-auth-legal-hint">{{ __('legal.inline_jurisdiction_hint') }}</p>
</div>
