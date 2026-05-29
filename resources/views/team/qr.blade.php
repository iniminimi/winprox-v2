<x-layouts.print :title="$team->name">
    <div class="wp-qr-page">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ $team->name }}</h1>
            <p class="wp-muted">{{ __('team.qr.subtitle') }}</p>
        </div>

        <div class="wp-qr-frame">
            {!! $qrSvg !!}
        </div>

        <p class="wp-qr-url">{{ $url }}</p>

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('team.qr.print') }}</button>
        </div>
    </div>
</x-layouts.print>
