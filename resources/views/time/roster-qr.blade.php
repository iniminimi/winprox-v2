<x-layouts.print :title="__('time.roster.qr_title')">
    <div class="wp-qr-page">
        <x-wp-page-head-title
            :title="__('time.roster.qr_title')"
            :subtitle="__('time.roster.qr_subtitle')"
        />

        <div class="wp-qr-frame">
            @include('partials.qr-print-code', ['qrSvg' => $qrSvg, 'centerLogoUrl' => $centerLogoUrl])
        </div>

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('time.clock_points.qr.print') }}</button>
        </div>
    </div>
</x-layouts.print>
