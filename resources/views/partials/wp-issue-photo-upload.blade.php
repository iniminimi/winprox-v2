@props([
    'model' => 'photos',
    'removeMethod' => null,
    'max' => 4,
    'photoAlt' => null,
    /** QR-portaal: camera voorrang op mobiel. Beheer: bestandskiezer (geen capture). */
    'preferCamera' => false,
    'hint' => null,
])

@php
    $uploadProperty = $model;
    $removeMethod ??= $uploadProperty === 'completingPhotos' ? 'removeCompletingPhoto' : 'removePhoto';
    $photoAlt ??= __('portal.report.photos.add');
@endphp

{{--
    Foto-upload golden path (WINPROX_RULES.md §7).
    Compressie + queue: resources/js/image-upload-compress.js (V1-baseline).
    Geen wire:model op file-input; wire:ignore op de hele area.
--}}
<div
    class="wp-photo-upload-area"
    wire:ignore
    wire:key="photo-area-{{ $uploadProperty }}"
    data-wp-photo-remove-method="{{ $removeMethod }}"
    data-wp-photo-max="{{ $max }}"
    x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
>
    <div class="wp-photo-grid wp-photo-grid--gallery">
        <div wire:ignore data-wp-photo-preview-root style="display:contents;"></div>

        <div data-wp-photo-picker>
            <label class="wp-photo-add" style="width:96px;height:96px;display:flex;align-items:center;justify-content:center;flex-direction:column;border:2px dashed var(--wp-border, #d1d5db);border-radius:10px;background:var(--wp-surface-muted, #f9fafb);margin:0;">
                <input
                    type="file"
                    data-wp-photo-compress
                    data-wp-photo-upload-prop="{{ $uploadProperty }}"
                    aria-label="{{ $photoAlt }}"
                    multiple
                    accept="image/*"
                    @if ($preferCamera) capture="environment" @endif
                    hidden
                >

                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>

                <span class="wp-hint" style="font-size:9px;font-weight:700;margin-top:3px;margin-bottom:0;">
                    {{ $photoAlt }}
                </span>
            </label>
        </div>
    </div>

    <p class="wp-hint">{{ $hint ?? __('portal.report.photos.hint') }}</p>
</div>
