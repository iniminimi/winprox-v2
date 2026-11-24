@if ($photos->isNotEmpty())
    <div class="wp-cluster wp-cluster--tight">
        @foreach ($photos as $photo)
            <a
                href="{{ route('absence-photos.file', $photo) }}"
                target="_blank"
                rel="noopener"
                class="wp-photo-thumb"
                wire:key="absence-photo-{{ $photo->id }}"
            >
                <img src="{{ route('absence-photos.file', $photo) }}" alt="{{ __('time.portal.absence.photos_add') }}">
            </a>
        @endforeach
    </div>
@endif
