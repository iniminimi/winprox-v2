@props([
    'csvUrl',
    'printUrl',
])

<div
    class="wp-list-export"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="btn btn--surface btn--sm"
        @click="open = !open"
        :aria-expanded="open"
        aria-haspopup="menu"
    >
        {{ __('reports.download') }}
    </button>
    <div
        class="wp-list-export__menu wp-card"
        x-show="open"
        x-cloak
        x-transition
        role="menu"
    >
        <a href="{{ $csvUrl }}" class="wp-list-export__item" role="menuitem" download>
            {{ __('reports.format.csv') }}
        </a>
        <a
            href="{{ $printUrl }}"
            class="wp-list-export__item"
            role="menuitem"
            target="_blank"
            rel="noopener noreferrer"
        >
            {{ __('reports.format.print') }}
        </a>
    </div>
</div>
