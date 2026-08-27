@props([
    'icon',
    'title',
    'helpPage',
    'refType' => 'issue',
    'refId',
    'headline' => '',
    'address' => '',
    'routeName',
    'currentId',
    'navLabel' => '',
    'firstId' => null,
    'prevId' => null,
    'nextId' => null,
    'lastId' => null,
])

<div class="wp-page-head">
    <div class="wp-stack-tight wp-grow">
        <x-wp-page-head-title :icon="$icon" :title="$title" :help-page="$helpPage">
            <x-slot:toolbar>
                <x-wp-detail-nav
                    :route-name="$routeName"
                    :current-id="$currentId"
                    :nav-label="$navLabel"
                    :first-id="$firstId"
                    :prev-id="$prevId"
                    :next-id="$nextId"
                    :last-id="$lastId"
                />
            </x-slot:toolbar>
        </x-wp-page-head-title>

        <x-wp-ref-nr :type="$refType" :id="$refId" />

        @if ($headline !== '')
            <div class="wp-row">
                <p class="wp-section-title wp-grow">{{ $headline }}</p>
                @isset($headlineActions)
                    <div class="wp-chip-row">{{ $headlineActions }}</div>
                @endisset
            </div>
        @elseif (isset($headlineActions))
            <div class="wp-row">
                <div class="wp-chip-row">{{ $headlineActions }}</div>
            </div>
        @endif
        @if ($address !== '')
            <p class="wp-muted">{{ $address }}</p>
        @endif

        @if (isset($meta))
            <div class="wp-cluster">{{ $meta }}</div>
        @endif
    </div>
</div>
