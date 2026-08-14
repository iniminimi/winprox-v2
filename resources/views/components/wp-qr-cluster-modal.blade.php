{{-- QR-cluster: printknop, A6/A5/A4-afdrukbladen, optioneel vernieuwen. --}}
@props([
    'closeMethod',
    'titleId',
    'title',
    'subtitle' => null,
    'printUrl' => null,
    'printLabel' => null,
    'formats' => [],
    'generating',
    'downloadFailed',
    'renewMethod' => null,
    'renewLabel' => null,
    'renewConfirm' => null,
])

<x-wp-modal :closeMethod="$closeMethod" :aria-labelledby="$titleId">
    <div class="wp-card wp-card-pad wp-stack wp-modal-card">
        <div class="wp-modal-head">
            <h2 id="{{ $titleId }}" class="wp-section-title">{{ $title }}</h2>
            <x-wp-modal-close wire:click="{{ $closeMethod }}" />
        </div>

        @if ($subtitle)
            <p class="wp-muted">{{ $subtitle }}</p>
        @endif

        @if ($printUrl)
            <div class="wp-modal-section">
                <div class="wp-stack-tight">
                    <h3 class="wp-label">{{ __('common.qr.print_heading') }}</h3>
                    <p class="wp-muted">{{ __('common.qr.print_hint') }}</p>
                </div>
                <div class="wp-cluster">
                    <a href="{{ $printUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn--surface">
                        {{ $printLabel }}
                    </a>
                </div>
            </div>
        @endif

        @if ($formats !== [])
            <div class="wp-modal-section">
                <div class="wp-stack-tight">
                    <h3 class="wp-label">{{ __('common.qr.pack_heading') }}</h3>
                    <p class="wp-muted">{{ __('common.qr.pack_hint') }}</p>
                </div>

                <div
                    class="wp-list wp-list--entity-rows"
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
                                this.error = exception?.message || @js($downloadFailed);
                            } finally {
                                this.downloading = null;
                            }
                        },
                    }"
                >
                    @foreach ($formats as $format)
                        <button
                            type="button"
                            class="wp-issue-row"
                            wire:key="qr-cluster-format-{{ $format['key'] }}"
                            @click="download(@js($format['url']), @js($format['key']))"
                            :disabled="downloading !== null"
                            :aria-busy="downloading === @js($format['key'])"
                        >
                            <span class="wp-issue-card-title">{{ $format['title'] }}</span>
                            <span class="wp-issue-card-meta" x-show="downloading !== @js($format['key'])">{{ $format['size'] }}</span>
                            <span class="wp-muted wp-cluster" x-show="downloading === @js($format['key'])" x-cloak>
                                <x-wp-spinner size="sm" :visible="true" />
                                <span>{{ $generating }}</span>
                            </span>
                        </button>
                    @endforeach

                    <p class="wp-error" x-show="error" x-text="error" x-cloak></p>
                </div>
            </div>
        @endif

        @if ($renewMethod)
            <div class="wp-modal-section">
                <div class="wp-stack-tight">
                    <h3 class="wp-label">{{ __('common.qr.renew_heading') }}</h3>
                    <p class="wp-muted">{{ __('common.qr.renew_hint') }}</p>
                </div>
                <div class="wp-cluster">
                    <button
                        type="button"
                        class="btn btn--surface"
                        wire:click="{{ $renewMethod }}"
                        wire:confirm="{{ $renewConfirm }}"
                    >
                        {{ $renewLabel }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-wp-modal>
