<x-layouts.app>
    <div class="wp-page-center">
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-card-icon wp-card-icon--warning">
                <x-wp-icon name="lock" />
            </div>
            
            <h1>{{ __('qr.unassigned_no_permission.title') }}</h1>
            <p>{{ __('qr.unassigned_no_permission.message') }}</p>
            
            <div class="wp-card-section">
                <dl class="wp-key-value">
                    <dt>{{ __('qr.unassigned_no_permission.sticker') }}</dt>
                    <dd><code>{{ $stickerNumber }}</code></dd>
                </dl>
            </div>
            
            <div class="wp-card-actions">
                <a href="{{ route('dashboard') }}" class="btn btn--primary">
                    {{ __('qr.unassigned_no_permission.dashboard') }}
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
