@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('common.pagination.nav_label') }}" class="wp-pagination__nav">
        <div class="wp-pagination__mobile">
            @if ($paginator->onFirstPage())
                <span class="btn btn--ghost btn--sm" aria-disabled="true">{{ __('common.pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn--ghost btn--sm">{{ __('common.pagination.previous') }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn--ghost btn--sm">{{ __('common.pagination.next') }}</a>
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
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="wp-pagination__control" aria-label="{{ __('common.pagination.previous') }}">‹</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="wp-pagination__ellipsis">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="wp-pagination__page is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="wp-pagination__page" aria-label="{{ __('common.pagination.go_to_page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="wp-pagination__control" aria-label="{{ __('common.pagination.next') }}">›</a>
                @else
                    <span class="wp-pagination__control" aria-disabled="true" aria-label="{{ __('common.pagination.next') }}">›</span>
                @endif
            </div>
        </div>
    </nav>
@endif
