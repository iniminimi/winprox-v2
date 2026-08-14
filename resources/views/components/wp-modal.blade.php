{{-- Central modal wrapper with Esc-key listener for closing --}}
{{-- Usage: <x-wp-modal closeMethod="closeCreateModal"> ...modal content... </x-wp-modal> --}}
<div class="wp-modal" role="dialog" aria-modal="true"
     x-on:keydown.escape.window="$wire.{{ $closeMethod }}()"
     {{ $attributes }}>
    {{ $slot }}
</div>
