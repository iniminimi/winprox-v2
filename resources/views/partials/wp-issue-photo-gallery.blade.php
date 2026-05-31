{{--
  Galerij van opgeslagen meldingsfoto's (beheer): kleine thumbnails, lightbox bij klik.
  Ontbrekende bestanden op disk worden niet getoond.
--}}
@props([
    'photos',
    'wireKeyPrefix' => 'photo',
])

@php
    $visiblePhotos = collect($photos)->filter(
        fn ($photo) => $photo instanceof \App\Models\IssuePhoto && $photo->hasPublicFile(),
    );
@endphp

@if ($visiblePhotos->isNotEmpty())
    <div
        class="wp-photo-gallery"
        x-data="{ lightboxSrc: null }"
        @keydown.escape.window="lightboxSrc = null"
    >
        <div class="wp-photo-grid wp-photo-grid--gallery">
            @foreach ($visiblePhotos as $photo)
                <button
                    type="button"
                    class="wp-photo-thumb"
                    wire:key="{{ $wireKeyPrefix }}-{{ $photo->id }}"
                    @click="lightboxSrc = @js($photo->publicUrl())"
                    aria-label="{{ __('issues.show.photo_enlarge') }}"
                >
                    <img
                        src="{{ $photo->publicUrl() }}"
                        alt=""
                        width="80"
                        height="80"
                        loading="lazy"
                        x-on:error="$el.closest('.wp-photo-thumb')?.remove()"
                    >
                </button>
            @endforeach
        </div>

        <div
            class="wp-photo-lightbox"
            x-show="lightboxSrc"
            x-cloak
            x-transition.opacity
            role="dialog"
            aria-modal="true"
            @click="lightboxSrc = null"
        >
            <img :src="lightboxSrc" alt="" @click.stop>
        </div>
    </div>
@endif
