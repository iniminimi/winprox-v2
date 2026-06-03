<x-layouts.print :title="$team->name">
    <div class="wp-qr-page">
        <x-wp-page-head-title
            :title="$team->name"
            :subtitle="__('team.qr.subtitle')"
        />

        <div class="wp-qr-frame">
            @include('partials.qr-print-code', ['qrSvg' => $qrSvg, 'centerLogoUrl' => $centerLogoUrl])
        </div>

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('team.qr.print') }}</button>
        </div>
    </div>
</x-layouts.print>
