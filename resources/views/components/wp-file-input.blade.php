@props([
    'wireModel',
    'id' => null,
    'accept' => null,
])

<div
    class="wp-file-input"
    x-data="{ fileName: '', emptyLabel: @js(__('common.file.none_selected')) }"
    x-on:saved.window="fileName = ''; if ($refs.native) { $refs.native.value = '' }"
>
    <input
        type="file"
        x-ref="native"
        @if ($id) id="{{ $id }}" @endif
        class="wp-file-input-native"
        wire:model="{{ $wireModel }}"
        @if ($accept) accept="{{ $accept }}" @endif
        @change="fileName = $event.target.files[0]?.name ?? ''"
        {{ $attributes }}
    >
    <label @if ($id) for="{{ $id }}" @endif class="btn btn--surface btn--sm wp-file-input-trigger">
        {{ __('common.file.browse') }}
    </label>
    <span class="wp-file-input-name wp-muted wp-text-sm" x-text="fileName || emptyLabel"></span>
</div>
