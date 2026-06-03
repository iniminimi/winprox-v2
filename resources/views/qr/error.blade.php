<x-layouts.guest>
    <div class="wp-page-center">
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-card-icon wp-card-icon--danger">
                <x-wp-icon name="alert-circle" />
            </div>
            
            <h1>{{ __('qr.error.title') }}</h1>
            <p>{{ __('qr.error.message') }}</p>
            
            <div class="wp-card-section">
                <dl class="wp-key-value">
                    <dt>{{ __('qr.error.sticker') }}</dt>
                    <dd><code>{{ $stickerNumber }}</code></dd>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.guest>
