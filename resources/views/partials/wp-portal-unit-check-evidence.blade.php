{{-- Optionele opmerking + foto's bij unit check (per unit, niet per checklistpunt). --}}
@props([
    'descriptionId' => 'check-description',
    'preferCamera' => true,
    'storeLocal' => false,
])

<div class="wp-field">
    <label class="wp-label" for="{{ $descriptionId }}">{{ __('portal.unit_check.note') }}</label>
    <textarea
        id="{{ $descriptionId }}"
        class="wp-textarea"
        rows="3"
        maxlength="500"
        wire:model="checkDescription"
        data-wp-check-description
        placeholder="{{ __('portal.unit_check.note_placeholder') }}"
    ></textarea>
    @error('checkDescription') <p class="wp-error">{{ $message }}</p> @enderror
</div>
<div class="wp-field">
    <label class="wp-label">{{ __('portal.unit_check.photos') }}</label>
    @include('partials.wp-issue-photo-upload', [
        'model' => 'checkPhotos',
        'removeMethod' => 'removeCheckPhoto',
        'preferCamera' => $preferCamera,
        'storeLocal' => $storeLocal,
        'photoAltKey' => 'portal.unit_check.photos',
    ])
    @error('checkPhotos') <p class="wp-error">{{ $message }}</p> @enderror
    @error('checkPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
</div>
