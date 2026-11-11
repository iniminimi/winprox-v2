<div class="wp-stack" data-manual-capture="photo-album">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="document"
                :title="__('photo_album.title')"
                help-page="photo-album"
                :subtitle="__('photo_album.subtitle')"
            />
        </div>
    </div>

    @if ($units->isEmpty())
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('photo_album.no_units') }}</p>
        </div>
    @else
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-cluster wp-cluster--between wp-cluster--wrap">
                <p class="wp-section-title">{{ __('photo_album.photos_title') }}</p>
                <div class="wp-cluster wp-cluster--tight">
                    <select class="wp-select wp-select--compact" wire:model.live="unitId" aria-label="{{ __('photo_album.filters.unit') }}">
                        @foreach ($units as $albumUnit)
                            <option value="{{ $albumUnit->id }}" @selected($unit?->id === $albumUnit->id)>
                                {{ $albumUnit->localizedName() }}@if ($albumUnit->location) — {{ $albumUnit->location->name ?: $albumUnit->location->address }}@endif
                            </option>
                        @endforeach
                    </select>
                    <select class="wp-select wp-select--compact" wire:model.live="day" aria-label="{{ __('photo_album.filters.day') }}">
                        @foreach ($days as $albumDay)
                            <option value="{{ $albumDay }}" @selected($day === $albumDay)>
                                {{ \Carbon\CarbonImmutable::parse($albumDay)->translatedFormat('l d-m-Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="wp-hint">{{ __('photo_album.retention_hint', ['days' => count($days)]) }}</p>

            @if ($photos->isEmpty())
                <p class="wp-muted">{{ __('photo_album.empty') }}</p>
            @else
                <div class="wp-photo-grid wp-photo-grid--gallery">
                    @foreach ($photos as $photo)
                        <div class="wp-stack-tight" wire:key="album-photo-{{ $photo->id }}">
                            <label class="wp-photo-thumb">
                                <img src="{{ route('photo-album.photos.show', $photo) }}" alt="" width="80" height="80" loading="lazy">
                            </label>
                            <label class="wp-check">
                                <input type="checkbox" value="{{ $photo->id }}" wire:model.live="selectedPhotoIds">
                                <span>{{ $photo->created_at?->format('H:i') }}</span>
                            </label>
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="deletePhoto({{ $photo->id }})"
                                    wire:confirm="{{ __('photo_album.delete_confirm') }}">
                                {{ __('photo_album.delete') }}
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
            @error('selectedPhotoIds') <p class="wp-error">{{ $message }}</p> @enderror
            @error('unitId') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <form class="wp-card wp-card-pad wp-stack" wire:submit="send">
            <p class="wp-section-title">{{ __('photo_album.send.title') }}</p>
            <p class="wp-hint">{{ __('photo_album.send.hint', ['max' => $maxPerMail]) }}</p>

            <label class="wp-field">
                <span class="wp-label">{{ __('photo_album.send.email') }}</span>
                <input type="email" class="wp-input" wire:model="recipientEmail" list="photo-album-recipients" autocomplete="off" maxlength="255">
                <datalist id="photo-album-recipients">
                    @foreach ($recipients as $recipient)
                        <option value="{{ $recipient->email }}">{{ $recipient->description }}</option>
                    @endforeach
                </datalist>
                @error('recipientEmail') <span class="wp-error">{{ $message }}</span> @enderror
            </label>

            <label class="wp-field">
                <span class="wp-label">{{ __('photo_album.send.description') }}</span>
                <input type="text" class="wp-input" wire:model="recipientDescription" maxlength="500" placeholder="{{ __('photo_album.send.description_placeholder') }}">
                @error('recipientDescription') <span class="wp-error">{{ $message }}</span> @enderror
            </label>

            <div class="wp-cluster wp-cluster--wrap">
                <button type="submit" class="btn btn--primary" @disabled(count($selectedPhotoIds) === 0)>
                    {{ __('photo_album.send.submit', ['count' => count($selectedPhotoIds)]) }}
                </button>
                @if ($statusMessage)
                    <span class="wp-pill wp-pill--done">{{ $statusMessage }}</span>
                @endif
            </div>
        </form>

        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-section-title">{{ __('photo_album.recipients.title') }}</p>
            @forelse ($recipients as $recipient)
                <div class="wp-cluster wp-cluster--between wp-cluster--wrap" wire:key="album-recipient-{{ $recipient->id }}">
                    @if ($editingRecipientId === $recipient->id)
                        <div class="wp-stack-tight wp-grow">
                            <strong>{{ $recipient->email }}</strong>
                            <input type="text" class="wp-input" wire:model="editingRecipientDescription" maxlength="500" placeholder="{{ __('photo_album.send.description_placeholder') }}">
                            @error('editingRecipientDescription') <span class="wp-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="wp-cluster wp-cluster--tight">
                            <button type="button" class="btn btn--primary btn--sm" wire:click="saveRecipient">{{ __('common.button.save') }}</button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="cancelEditRecipient">{{ __('common.button.cancel') }}</button>
                        </div>
                    @else
                        <div class="wp-stack-tight">
                            <strong>{{ $recipient->email }}</strong>
                            @if (filled($recipient->description))
                                <span class="wp-muted">{{ $recipient->description }}</span>
                            @endif
                        </div>
                        <div class="wp-cluster wp-cluster--tight">
                            <button type="button" class="btn btn--surface btn--sm" wire:click="useRecipient({{ $recipient->id }})">{{ __('photo_album.recipients.use') }}</button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="editRecipient({{ $recipient->id }})">{{ __('photo_album.recipients.edit') }}</button>
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="deleteRecipient({{ $recipient->id }})"
                                    wire:confirm="{{ __('photo_album.recipients.delete_confirm') }}">
                                {{ __('photo_album.recipients.delete') }}
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="wp-muted">{{ __('photo_album.recipients.empty') }}</p>
            @endforelse
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-section-title">{{ __('photo_album.sends.title') }}</p>
            @forelse ($sends as $albumSend)
                <div class="wp-cluster wp-cluster--between wp-cluster--wrap" wire:key="album-send-{{ $albumSend->id }}">
                    <div class="wp-stack-tight">
                        <strong>{{ $albumSend->recipient_email }}</strong>
                        <span class="wp-muted">
                            {{ __('photo_album.sends.line', [
                                'count' => $albumSend->photo_count,
                                'time' => $albumSend->created_at?->format('d-m-Y H:i'),
                                'user' => $albumSend->user?->name ?? '—',
                            ]) }}
                        </span>
                    </div>
                    <span class="wp-pill wp-pill--{{ $albumSend->status->pillVariant() }}">
                        @if ($albumSend->status === \App\Enums\UnitAlbumSendStatus::Queued && $albumSend->scheduled_at?->isFuture())
                            {{ __('photo_album.sends.status.scheduled', ['time' => $albumSend->scheduled_at->format('H:i')]) }}
                        @else
                            {{ __('photo_album.sends.status.'.$albumSend->status->value) }}
                        @endif
                    </span>
                </div>
            @empty
                <p class="wp-muted">{{ __('photo_album.sends.empty') }}</p>
            @endforelse
        </div>
    @endif
</div>
