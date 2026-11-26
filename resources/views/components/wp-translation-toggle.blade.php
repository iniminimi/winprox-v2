@props(['wireModel'])

<label class="wp-check wp-check--boxed">
    <input type="checkbox" wire:model.live="{{ $wireModel }}">
    <span>{{ __('common.translation_enable') }}</span>
</label>
