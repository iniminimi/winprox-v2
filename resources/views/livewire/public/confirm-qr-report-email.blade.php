<div class="wp-public-wrap wp-stack">
    <div class="wp-card wp-card-pad wp-stack">
        <h1 class="wp-welcome-h2">{{ __('portal.report.verify_email_title') }}</h1>
        <p class="{{ $status === 'ok' ? 'wp-text-body' : 'wp-error' }}">{{ $message }}</p>
        @if ($status === 'ok' && $unitPortalUrl)
            <a href="{{ $unitPortalUrl }}" class="btn btn--primary">
                {{ __('portal.report.verify_email_back') }}
            </a>
        @endif
    </div>
</div>
