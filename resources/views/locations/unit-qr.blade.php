<x-layouts.print :title="$unit->name">
    <div class="wp-qr-page">
        <x-wp-page-head-title
            icon="units"
            :title="$unit->name"
            :subtitle="__('locations.unit_qr_subtitle')"
        />

        <div class="wp-qr-frame">
            @include('partials.qr-print-code', ['qrSvg' => $qrSvg, 'centerLogoUrl' => $centerLogoUrl])
        </div>

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('common.button.print') }}</button>
        </div>
    </div>
</x-layouts.print>
