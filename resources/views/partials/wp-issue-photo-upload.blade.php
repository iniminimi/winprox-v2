@php
    $model = $model ?? 'photos';
    $removeMethod = $removeMethod ?? null;
    $max = $max ?? 4;
    $photoAltKey = $photoAltKey ?? 'portal.report.photos.add';
    $preferCamera = $preferCamera ?? false;
    $hintKey = $hintKey ?? null;
    $uploadLocale = app()->getLocale();

    $uploadProperty = $model;
    $removeMethod ??= $uploadProperty === 'completingPhotos' ? 'removeCompletingPhoto' : 'removePhoto';
    $photoAltLabel = __($photoAltKey);
@endphp

{{--
    Foto-upload golden path (WINPROX_RULES.md §7).
    Compressie + queue: resources/js/image-upload-compress.js (V1-baseline).
    Geen wire:model op file-input; wire:ignore alleen op de interactieve picker.
--}}
<div class="wp-photo-upload">
    <div
        class="wp-photo-upload-area"
        wire:ignore
        wire:key="photo-area-{{ $uploadProperty }}-{{ $uploadLocale }}"
        data-wp-photo-remove-method="{{ $removeMethod }}"
        data-wp-photo-max="{{ $max }}"
        x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
    >
        <div class="wp-photo-grid wp-photo-grid--gallery">
            <div wire:ignore class="wp-photo-preview-root" data-wp-photo-preview-root></div>

            <div data-wp-photo-picker>
                <label class="wp-photo-add">
                    <input
                        type="file"
                        data-wp-photo-compress
                        data-wp-photo-upload-prop="{{ $uploadProperty }}"
                        aria-label="{{ $photoAltLabel }}"
                        multiple
                        accept="image/*"
                        @if ($preferCamera) capture="environment" @endif
                        hidden
                    >

                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>

                    <span class="wp-hint wp-photo-add-label">{{ $photoAltLabel }}</span>
                </label>
            </div>
        </div>
    </div>

    <p class="wp-hint">{{ $hintKey ? __($hintKey) : __('portal.report.photos.hint') }}</p>
</div>
