<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('photo_album.title')"
        help-page="photo-album"
        :subtitle="__('photo_album.subtitle')"
    />

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-filter-cell">
            <span class="wp-filter-inline-label">{{ __('photo_album.unit') }}</span>
            <select class="wp-select wp-select--compact" wire:model.live="unitId" aria-label="{{ __('photo_album.unit') }}">
                <option value="">{{ __('photo_album.choose_unit') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->localizedName() }}</option>
                @endforeach
            </select>
        </div>
        @error('unitId') <p class="wp-error">{{ $message }}</p> @enderror

        @if ($unitId)
            @if ($photos->isEmpty())
                <p class="wp-muted">{{ __('photo_album.empty') }}</p>
            @else
                <div class="wp-stack-tight">
                    @foreach ($photos as $photo)
                        <label class="wp-check">
                            <input type="checkbox" value="{{ $photo->id }}" wire:model="photoIds">
                            <span>
                                <img
                                    src="{{ route('photo-album.photos.show', $photo) }}"
                                    alt=""
                                    width="72"
                                    height="72"
                                >
                                {{ $photo->taken_on?->translatedFormat('j M Y') }}
                                @if ($photo->worker)
                                    · {{ $photo->worker->displayName() }}
                                @endif
                            </span>
                        </label>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deletePhoto({{ $photo->id }})">
                            {{ __('photo_album.delete') }}
                        </button>
                    @endforeach
                </div>
                @error('photoIds') <p class="wp-error">{{ $message }}</p> @enderror
            @endif

            @if ($recipients->isNotEmpty())
                <div class="wp-stack-tight">
                    <p class="wp-label">{{ __('photo_album.recipients') }}</p>
                    @foreach ($recipients as $recipient)
                        <div class="wp-cluster">
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="useRecipient({{ $recipient->id }})">
                                {{ $recipient->email }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteRecipient({{ $recipient->id }})">
                                {{ __('photo_album.delete') }}
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="wp-stack">
                <div class="wp-field">
                    <label class="wp-label" for="album-email">{{ __('photo_album.email') }}</label>
                    <input id="album-email" type="email" class="wp-input" wire:model="email" autocomplete="email">
                    @error('email') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="album-description">{{ __('photo_album.description') }}</label>
                    <input id="album-description" type="text" class="wp-input" wire:model="description" maxlength="500">
                    @error('description') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">{{ __('photo_album.send') }}</button>
            </form>

            @if ($sends->isNotEmpty())
                <ul class="wp-stack-tight">
                    @foreach ($sends as $send)
                        <li>
                            <span class="wp-pill wp-pill--{{ $send->status->pillVariant() }}">{{ __('photo_album.status.'.$send->status->value) }}</span>
                            {{ $send->recipient_email }}
                            · {{ $send->photo_count }}
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</div>
