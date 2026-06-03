<x-layouts.guest>
    <div class="wp-page-center">
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-card-icon wp-card-icon--warning">
                <x-wp-icon name="qr" />
            </div>
            
            <h1>{{ __('qr.unassigned_guest.title') }}</h1>
            <p>{{ __('qr.unassigned_guest.message') }}</p>
            
            <div class="wp-card-section">
                <dl class="wp-key-value">
                    <dt>{{ __('qr.unassigned_guest.sticker') }}</dt>
                    <dd><code>{{ $stickerNumber }}</code></dd>
                </dl>
            </div>
            
            <div class="wp-card-actions">
                <a href="{{ route('login') }}" class="btn btn--primary">
                    {{ __('qr.unassigned_guest.login') }}
                </a>
            </div>
        </div>
    </div>
</x-layouts.guest>
