<x-layouts.print :title="$location->name">
    <div class="wp-qr-page">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ $location->name }}</h1>
            <p class="wp-muted">{{ __('locations.location_qr_subtitle') }}</p>
        </div>

        <div class="wp-qr-frame">
            {!! $qrSvg !!}
        </div>

        <p class="wp-qr-url">{{ $url }}</p>

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('common.button.print') }}</button>
        </div>
    </div>
</x-layouts.print>
