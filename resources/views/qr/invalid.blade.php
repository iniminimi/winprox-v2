<x-layouts.guest>
    <div class="wp-page-center">
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-card-icon wp-card-icon--neutral">
                <x-wp-icon name="alert-triangle" />
            </div>

            <h1>{{ __('qr.invalid.title') }}</h1>
            <p>{{ __('qr.invalid.message') }}</p>

            <div class="wp-card-actions">
                <a href="{{ route('welcome') }}" class="btn btn--primary">
                    {{ __('qr.invalid.welcome') }}
                </a>
            </div>
        </div>
    </div>
</x-layouts.guest>
