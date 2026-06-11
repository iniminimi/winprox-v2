<x-layouts.print :title="$team->name">
    <div class="wp-qr-page">
        <x-wp-page-head-title
            :title="$team->name"
            :subtitle="__('team.qr.subtitle')"
        />

        <div class="wp-qr-frame">
            @include('partials.qr-print-code', ['qrSvg' => $qrSvg, 'centerLogoUrl' => $centerLogoUrl])
        </div>

        @if (session('team_qr_email_sent'))
            <div class="wp-flash wp-flash--success wp-no-print">{{ __('team.qr.email_sent', ['email' => session('team_qr_email_sent')]) }}</div>
        @endif

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('team.qr.print') }}</button>
            <livewire:team.qr-email :team="$team" :portal-url="$url" />
        </div>
    </div>
</x-layouts.print>
