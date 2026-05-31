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
    <nav class="wp-cluster" aria-label="{{ $navLabel }}">
        @if ($firstId && $firstId !== $currentId)
            <a href="{{ route($routeName, $firstId) }}" class="btn btn--ghost btn--sm"
               aria-label="{{ __('common.detail_nav.first') }}">&laquo;</a>
        @endif
        @if ($prevId)
            <a href="{{ route($routeName, $prevId) }}" class="btn btn--ghost btn--sm"
               aria-label="{{ __('common.detail_nav.prev') }}">&lsaquo;</a>
        @endif
        @if ($nextId)
            <a href="{{ route($routeName, $nextId) }}" class="btn btn--ghost btn--sm"
               aria-label="{{ __('common.detail_nav.next') }}">&rsaquo;</a>
        @endif
        @if ($lastId && $lastId !== $currentId)
            <a href="{{ route($routeName, $lastId) }}" class="btn btn--ghost btn--sm"
               aria-label="{{ __('common.detail_nav.last') }}">&raquo;</a>
        @endif
    </nav>
@endif
