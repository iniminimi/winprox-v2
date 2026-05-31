<x-layouts.print :title="$location->name">
    <div class="wp-qr-page">
        <x-wp-page-head-title
            icon="locations"
            :title="$location->name"
            :subtitle="__('locations.location_qr_subtitle')"
        />

        <div class="wp-qr-frame">
            @include('partials.qr-print-code', ['qrSvg' => $qrSvg, 'centerLogoUrl' => $centerLogoUrl])
        </div>

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('common.button.print') }}</button>
        </div>
    </div>
</x-layouts.print>
