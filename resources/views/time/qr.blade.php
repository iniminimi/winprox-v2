<x-layouts.print :title="$clockPoint->name">
    <div class="wp-qr-page">
        <x-wp-page-head-title
            :title="$clockPoint->name"
            :subtitle="__('time.clock_points.qr.subtitle')"
        />

        @if ($clockPoint->location)
            <p class="wp-muted">{{ $clockPoint->location->localizedName() }}</p>
        @endif

        <div class="wp-qr-frame">
            @include('partials.qr-print-code', ['qrSvg' => $qrSvg, 'centerLogoUrl' => $centerLogoUrl])
        </div>

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('time.clock_points.qr.print') }}</button>
        </div>
    </div>
</x-layouts.print>
