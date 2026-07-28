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

        <div
            class="wp-qr-actions wp-no-print"
            x-data="{
                downloading: null,
                error: null,
                async download(url, key) {
                    if (this.downloading) {
                        return;
                    }

                    this.downloading = key;
                    this.error = null;

                    try {
                        await window.wpDownloadQrPackUrl(url);
                    } catch (exception) {
                        this.error = exception?.message || @js(__('time.clock_points.qr.pack.download_failed'));
                    } finally {
                        this.downloading = null;
                    }
                },
            }"
        >
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('time.clock_points.qr.print') }}</button>

            @foreach ($qrPackTemplates as $template)
                <button
                    type="button"
                    class="btn"
                    @click="download(@js(route('time.clock-points.qr-pack', ['clockPoint' => $clockPoint, 'template' => $template->value])), @js($template->value))"
                    :disabled="downloading !== null"
                    :aria-busy="downloading === @js($template->value)"
                >
                    <span x-show="downloading !== @js($template->value)">{{ __('time.clock_points.qr.pack.'.$template->value) }}</span>
                    <span class="wp-cluster" x-show="downloading === @js($template->value)" x-cloak>
                        <x-wp-spinner size="sm" :visible="true" />
                        <span>{{ __('time.clock_points.qr.pack.generating') }}</span>
                    </span>
                </button>
            @endforeach

            <p class="wp-error" x-show="error" x-text="error" x-cloak></p>
        </div>
    </div>
</x-layouts.print>
