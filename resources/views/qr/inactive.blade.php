<x-layouts.guest>
    <div class="wp-page-center">
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-card-icon wp-card-icon--neutral">
                <x-wp-icon name="ban" />
            </div>
            
            <h1>{{ __('qr.inactive.title') }}</h1>
            <p>{{ __('qr.inactive.message') }}</p>
            
            <div class="wp-card-section">
                <dl class="wp-key-value">
                    <dt>{{ __('qr.inactive.sticker') }}</dt>
                    <dd><code>{{ $stickerNumber }}</code></dd>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.guest>
