@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div class="wp-pagination">
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ __('common.pagination.nav_label') }}" class="wp-pagination__nav">
            <div class="wp-pagination__mobile">
                @if ($paginator->onFirstPage())
                    <span class="btn btn--ghost btn--sm" aria-disabled="true">{{ __('common.pagination.previous') }}</span>
                @else
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">
                        {{ __('common.pagination.previous') }}
                    </button>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">
                        {{ __('common.pagination.next') }}
                    </button>
                @else
                    <span class="btn btn--ghost btn--sm" aria-disabled="true">{{ __('common.pagination.next') }}</span>
                @endif
            </div>

            <div class="wp-pagination__desktop">
                <p class="wp-pagination__summary wp-muted">
                    {{ __('common.pagination.showing') }}
                    @if ($paginator->firstItem())
                        <span class="wp-pagination__num">{{ $paginator->firstItem() }}</span>
                        {{ __('common.pagination.to') }}
                        <span class="wp-pagination__num">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {{ __('common.pagination.of') }}
                    <span class="wp-pagination__num">{{ $paginator->total() }}</span>
                    {{ __('common.pagination.results') }}
                </p>

                <div class="wp-pagination__pages">
                    @if ($paginator->onFirstPage())
                        <span class="wp-pagination__control" aria-disabled="true" aria-label="{{ __('common.pagination.previous') }}">‹</span>
                    @else
                        <button type="button" class="wp-pagination__control" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('common.pagination.previous') }}">‹</button>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="wp-pagination__ellipsis">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                    @if ($page == $paginator->currentPage())
                                        <span class="wp-pagination__page is-active" aria-current="page">{{ $page }}</span>
                                    @else
                                        <button type="button" class="wp-pagination__page" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('common.pagination.go_to_page', ['page' => $page]) }}">
                                            {{ $page }}
                                        </button>
                                    @endif
                                </span>
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <button type="button" class="wp-pagination__control" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('common.pagination.next') }}">›</button>
                    @else
                        <span class="wp-pagination__control" aria-disabled="true" aria-label="{{ __('common.pagination.next') }}">›</span>
                    @endif
                </div>
            </div>
        </nav>
    @endif
</div>
