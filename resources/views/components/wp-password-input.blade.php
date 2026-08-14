@props([
    'wireModel',
    'id' => null,
    'placeholder' => '',
    'autocomplete' => 'new-password',
])

<div class="wp-input-group" x-data="{ show: false }">
    <input
        type="password"
        :type="show ? 'text' : 'password'"
        @if ($id) id="{{ $id }}" @endif
        class="wp-input"
        wire:model="{{ $wireModel }}"
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        {{ $attributes }}
    >
    <button
        type="button"
        class="wp-input-reveal"
        @click="show = !show"
        :aria-label="show ? '{{ __('auth.hide_password') }}' : '{{ __('auth.show_password') }}'"
    >
        <x-wp-icon name="eye" class="wp-icon" x-show="!show" />
        <x-wp-icon name="eye-slash" class="wp-icon" x-show="show" x-cloak />
    </button>
</div>
