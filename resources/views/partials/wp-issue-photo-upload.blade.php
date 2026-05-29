@php($wpModel = $model ?? 'photos')
{{--
    Foto-upload golden path (§7). De file-input heeft GEEN wire:model: het
    comprimeren + uploaden gebeurt in resources/js/image-upload-compress.js.
    De area staat op wire:ignore zodat Livewire-morphs de previews niet wissen.
    Geef een ander $model mee om naar een andere Livewire-property te uploaden.
--}}
<div class="wp-photo-upload-area"
     wire:ignore
     wire:key="photo-area-{{ $wpModel }}"
     data-wp-photo-compress
     data-wp-model="{{ $wpModel }}"
     data-max="4">
    <div class="wp-photo-grid" data-wp-photo-previews></div>

    <label class="btn btn--ghost btn--block wp-photo-add">
        <span>{{ __('portal.report.photos.add') }}</span>
        <input type="file"
               class="wp-photo-input"
               accept="image/*"
               capture="environment"
               multiple
               hidden>
    </label>

    <p class="wp-hint">{{ __('portal.report.photos.hint') }}</p>
    <p class="wp-photo-status"
       data-wp-photo-status
       data-uploading="{{ __('portal.report.photos.uploading') }}"
       data-ready="{{ __('portal.report.photos.ready') }}"></p>
</div>
