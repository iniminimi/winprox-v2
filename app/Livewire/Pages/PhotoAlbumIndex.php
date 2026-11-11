<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Actions\PhotoAlbum\DeleteUnitAlbumPhotoAction;
use App\Actions\PhotoAlbum\DeleteUnitAlbumRecipientAction;
use App\Actions\PhotoAlbum\LoadUnitPhotoAlbumAction;
use App\Actions\PhotoAlbum\SendUnitAlbumPhotosAction;
use App\Actions\PhotoAlbum\UpdateUnitAlbumRecipientAction;
use App\Data\PhotoAlbum\SendUnitAlbumPhotosData;
use App\Http\Requests\PhotoAlbum\SendUnitAlbumPhotosRequest;
use App\Http\Requests\PhotoAlbum\UpdateUnitAlbumRecipientRequest;
use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumRecipient;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PhotoAlbumIndex extends Component
{
    #[Url(as: 'unit')]
    public ?int $unitId = null;

    #[Url(as: 'day')]
    public string $day = '';

    /** @var list<int|string> */
    public array $selectedPhotoIds = [];

    public string $recipientEmail = '';

    public string $recipientDescription = '';

    public ?int $editingRecipientId = null;

    public string $editingRecipientDescription = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', UnitAlbumPhoto::class);
    }

    public function updatedUnitId(): void
    {
        $this->resetSelection();
        $this->editingRecipientId = null;
    }

    public function updatedDay(): void
    {
        $this->resetSelection();
    }

    public function useRecipient(int $recipientId): void
    {
        $recipient = UnitAlbumRecipient::query()->find($recipientId);
        if ($recipient === null) {
            return;
        }
        $this->authorize('update', $recipient);
        $this->recipientEmail = (string) $recipient->email;
        $this->recipientDescription = (string) ($recipient->description ?? '');
        $this->resetErrorBag(['recipientEmail', 'recipientDescription']);
    }

    public function send(SendUnitAlbumPhotosAction $send): void
    {
        $this->authorize('send', UnitAlbumPhoto::class);
        $this->statusMessage = null;

        $input = [
            'unit_id' => $this->unitId ?? $this->currentUnitId(),
            'photo_ids' => array_values($this->selectedPhotoIds),
            'email' => trim($this->recipientEmail),
            'description' => $this->recipientDescription,
        ];

        $validator = Validator::make($input, SendUnitAlbumPhotosRequest::rulesFor(), SendUnitAlbumPhotosRequest::validationMessages());
        if ($validator->fails()) {
            $this->addMappedErrors($validator->errors()->getMessages());

            return;
        }

        try {
            $sends = $send->handle(
                SendUnitAlbumPhotosData::fromValidated($validator->validated()),
                (int) Tenancy::id(),
                (int) auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            $field = match ($e->getMessage()) {
                'photos_unavailable' => 'photo_ids',
                'unsubscribed' => 'email',
                default => 'unit_id',
            };
            $this->addMappedErrors([
                $field => [__('photo_album.errors.'.$e->getMessage(), ['email' => trim($this->recipientEmail)])],
            ]);

            return;
        }

        $last = $sends->last();
        $this->statusMessage = $last !== null && $last->scheduled_at?->isFuture()
            ? __('photo_album.send.scheduled', ['count' => $sends->count(), 'time' => $last->scheduled_at->format('H:i')])
            : __('photo_album.send.queued', ['count' => $sends->count()]);
        $this->resetSelection();
        $this->recipientEmail = '';
        $this->recipientDescription = '';
    }

    public function deletePhoto(int $photoId, DeleteUnitAlbumPhotoAction $delete): void
    {
        $photo = UnitAlbumPhoto::query()->find($photoId);
        if ($photo === null) {
            return;
        }
        $this->authorize('delete', $photo);
        $delete->handle($photo, (int) $photo->tenant_id, (int) auth()->id());
        $this->selectedPhotoIds = array_values(array_filter(
            $this->selectedPhotoIds,
            fn ($id) => (int) $id !== $photoId,
        ));
    }

    public function editRecipient(int $recipientId): void
    {
        $recipient = UnitAlbumRecipient::query()->find($recipientId);
        if ($recipient === null) {
            return;
        }
        $this->authorize('update', $recipient);
        $this->editingRecipientId = (int) $recipient->id;
        $this->editingRecipientDescription = (string) ($recipient->description ?? '');
    }

    public function cancelEditRecipient(): void
    {
        $this->editingRecipientId = null;
        $this->editingRecipientDescription = '';
        $this->resetErrorBag('editingRecipientDescription');
    }

    public function saveRecipient(UpdateUnitAlbumRecipientAction $update): void
    {
        $recipient = $this->editingRecipientId !== null
            ? UnitAlbumRecipient::query()->find($this->editingRecipientId)
            : null;
        if ($recipient === null) {
            $this->cancelEditRecipient();

            return;
        }
        $this->authorize('update', $recipient);

        $validated = $this->validate([
            'editingRecipientDescription' => UpdateUnitAlbumRecipientRequest::rulesFor()['description'],
        ], [
            'editingRecipientDescription.max' => __('photo_album.errors.description_max'),
        ]);

        $update->handle($recipient, $validated['editingRecipientDescription'], (int) $recipient->tenant_id, (int) auth()->id());
        $this->cancelEditRecipient();
    }

    public function deleteRecipient(int $recipientId, DeleteUnitAlbumRecipientAction $delete): void
    {
        $recipient = UnitAlbumRecipient::query()->find($recipientId);
        if ($recipient === null) {
            return;
        }
        $this->authorize('delete', $recipient);
        $delete->handle($recipient, (int) $recipient->tenant_id, (int) auth()->id());
        if ($this->editingRecipientId === $recipientId) {
            $this->cancelEditRecipient();
        }
    }

    public function render(LoadUnitPhotoAlbumAction $load): View
    {
        $album = $load->handle((int) Tenancy::id(), $this->unitId, $this->day !== '' ? $this->day : null);

        return view('livewire.pages.photo-album-index', [
            ...$album,
            'maxPerMail' => SendUnitAlbumPhotosAction::maxPerMail(),
        ]);
    }

    private function currentUnitId(): ?int
    {
        $unit = app(LoadUnitPhotoAlbumAction::class)->handle((int) Tenancy::id(), null, null)['unit'];

        return $unit?->id !== null ? (int) $unit->id : null;
    }

    private function resetSelection(): void
    {
        $this->selectedPhotoIds = [];
        $this->resetErrorBag(['selectedPhotoIds', 'recipientEmail', 'recipientDescription']);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function addMappedErrors(array $errors): void
    {
        $map = [
            'unit_id' => 'unitId',
            'photo_ids' => 'selectedPhotoIds',
            'email' => 'recipientEmail',
            'description' => 'recipientDescription',
        ];
        foreach ($errors as $field => $messages) {
            $key = $map[explode('.', $field)[0]] ?? 'selectedPhotoIds';
            foreach ($messages as $message) {
                $this->addError($key, $message);
            }
        }
    }
}
