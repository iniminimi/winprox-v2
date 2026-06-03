<x-layouts.guest>
    <div class="wp-page-center">
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-card-icon wp-card-icon--warning">
                <x-wp-icon name="alert-triangle" />
            </div>
            
            <h1>{{ __('qr.damaged.title') }}</h1>
            <p>{{ __('qr.damaged.message') }}</p>
            
            <div class="wp-card-section">
                <dl class="wp-key-value">
                    <dt>{{ __('qr.damaged.sticker') }}</dt>
                    <dd><code>{{ $stickerNumber }}</code></dd>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.guest>
