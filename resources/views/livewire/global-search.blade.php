<div
    class="wp-global-search"
    x-data="{
        open: @entangle('isOpen'),
        isMac: navigator.platform.toUpperCase().indexOf('MAC') >= 0,
        top: 0,
        syncTop() {
            const bar = this.$el.querySelector('.wp-search-bar');
            if (bar) {
                this.top = Math.round(bar.getBoundingClientRect().bottom) + 6;
            }
        },
    }"
    x-effect="if (open) syncTop()"
    @scroll.window.capture="if (open) syncTop()"
    @resize.window="if (open) syncTop()"
    @click.away="open = false"
    @keydown.escape.window="open = false"
>
    <div class="wp-search-bar">
        <div class="wp-search-input-wrapper">
            <x-wp-icon name="search" class="wp-search-icon" />
            <input
                type="text"
                wire:model.live.debounce.300ms="query"
                placeholder="{{ __('search.placeholder') }}"
                class="wp-search-input"
                @focus="open = true"
            />
            <button
                type="button"
                class="wp-search-clear"
                wire:click="close"
                x-show="query.length > 0"
                x-cloak
                aria-label="{{ __('common.button.clear') }}"
            >
                <x-wp-icon name="x-mark" class="wp-icon" />
            </button>
        </div>
    </div>

    @if ($results->isNotEmpty())
        <div class="wp-search-results" x-show="open" x-cloak :style="{ top: top + 'px' }" x-transition:enter="wp-search-results-enter" x-transition:enter-end="wp-search-results-enter-end" x-transition:leave="wp-search-results-leave" x-transition:leave-end="wp-search-results-leave-end">
            @foreach ($results as $type => $items)
                <div class="wp-search-section">
                    <h3 class="wp-search-section-title">{{ __('search.'.$type) }}</h3>
                    <div class="wp-search-list">
                        @foreach ($items as $item)
                            <a href="{{ $item['url'] }}" class="wp-search-item">
                                <div class="wp-search-item-content">
                                    <span class="wp-search-item-title">{{ $item['title'] }}</span>
                                    @if ($item['subtitle'])
                                        <span class="wp-search-item-subtitle">{{ $item['subtitle'] }}</span>
                                    @endif
                                </div>
                                <x-wp-icon name="arrow-right" class="wp-search-item-icon" />
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @elseif ($query !== '' && strlen($query) >= $minQueryLength)
        <div class="wp-search-results wp-search-results--empty" x-show="open" x-cloak :style="{ top: top + 'px' }">
            <p class="wp-search-empty">{{ __('search.no_results') }}</p>
        </div>
    @endif
</div>
