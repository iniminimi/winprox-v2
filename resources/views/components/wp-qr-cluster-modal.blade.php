{{-- QR-cluster: printpagina + A6/A5/A4-afdrukbladen, optioneel vernieuwen. --}}
@props([
    'closeMethod',
    'titleId',
    'title',
    'subtitle' => null,
    'printUrl' => null,
    'printLabel' => null,
    'printHint' => null,
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
            @if ($printUrl)
                <a href="{{ $printUrl }}" target="_blank" rel="noopener noreferrer" class="wp-issue-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $printLabel }}</p>
                        @if ($printHint)
                            <p class="wp-muted">{{ $printHint }}</p>
                        @endif
                    </div>
                </a>
            @endif

            @foreach ($formats as $format)
                <button
                    type="button"
                    class="wp-issue-row"
                    wire:key="qr-cluster-format-{{ $format['key'] }}"
                    @click="download(@js($format['url']), @js($format['key']))"
                    :disabled="downloading !== null"
                    :aria-busy="downloading === @js($format['key'])"
                >
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $format['title'] }}</p>
                        <p class="wp-muted" x-show="downloading !== @js($format['key'])">{{ $format['description'] }}</p>
                        <p class="wp-muted wp-cluster" x-show="downloading === @js($format['key'])" x-cloak>
                            <x-wp-spinner size="sm" :visible="true" />
                            <span>{{ $generating }}</span>
                        </p>
                    </div>
                </button>
            @endforeach

            @if ($renewMethod)
                <button
                    type="button"
                    class="wp-issue-row"
                    wire:click="{{ $renewMethod }}"
                    wire:confirm="{{ $renewConfirm }}"
                >
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $renewLabel }}</p>
                    </div>
                </button>
            @endif

            <p class="wp-error" x-show="error" x-text="error" x-cloak></p>
        </div>
    </div>
</x-wp-modal>
