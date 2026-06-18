@props([
    'routeName',
    'currentId',
    'navLabel' => '',
    'firstId' => null,
    'prevId' => null,
    'nextId' => null,
    'lastId' => null,
])

@php
    $hasNav = ($firstId && $firstId !== $currentId)
        || $prevId
        || $nextId
        || ($lastId && $lastId !== $currentId);
@endphp

@if ($hasNav)
    <nav class="wp-pagination__pages wp-detail-nav" aria-label="{{ $navLabel }}">
        @if ($firstId && $firstId !== $currentId)
            <a href="{{ route($routeName, $firstId) }}" class="wp-pagination__control"
               aria-label="{{ __('common.detail_nav.first') }}">«</a>
        @endif
        @if ($prevId)
            <a href="{{ route($routeName, $prevId) }}" class="wp-pagination__control"
               aria-label="{{ __('common.detail_nav.prev') }}">‹</a>
        @endif
        @if ($nextId)
            <a href="{{ route($routeName, $nextId) }}" class="wp-pagination__control"
               aria-label="{{ __('common.detail_nav.next') }}">›</a>
        @endif
        @if ($lastId && $lastId !== $currentId)
            <a href="{{ route($routeName, $lastId) }}" class="wp-pagination__control"
               aria-label="{{ __('common.detail_nav.last') }}">»</a>
        @endif
    </nav>
@endif
