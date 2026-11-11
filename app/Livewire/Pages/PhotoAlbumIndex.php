<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Actions\PhotoAlbum\DeleteUnitAlbumPhotoAction;
use App\Actions\PhotoAlbum\DeleteUnitAlbumRecipientAction;
use App\Actions\PhotoAlbum\ListUnitAlbumAction;
use App\Actions\PhotoAlbum\SendUnitAlbumPhotosAction;
use App\Actions\PhotoAlbum\UpdateUnitAlbumRecipientAction;
use App\Data\PhotoAlbum\SendUnitAlbumPhotosData;
use App\Http\Requests\PhotoAlbum\SendUnitAlbumPhotosRequest;
use App\Models\UnitAlbumPhoto;
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

    /** @var list<int> */
    public array $photoIds = [];

    public string $email = '';

    public string $description = '';

    public function mount(): void
    {
        $this->authorize('viewAny', UnitAlbumPhoto::class);
    }

    public function send(SendUnitAlbumPhotosAction $send): void
    {
        $this->authorize('send', UnitAlbumPhoto::class);

        $validated = Validator::make(
            [
                'unit_id' => $this->unitId,
                'photo_ids' => $this->photoIds,
                'email' => $this->email,
                'description' => $this->description,
            ],
            SendUnitAlbumPhotosRequest::rulesFor(),
            SendUnitAlbumPhotosRequest::validationMessages(),
        )->validate();

        try {
            $send->handle(
                SendUnitAlbumPhotosData::fromValidated($validated),
                (int) Tenancy::id(),
                (int) auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            $field = match ($e->getMessage()) {
                'photos_unavailable' => 'photoIds',
                'unsubscribed' => 'email',
                default => 'unitId',
            };
            $this->addError($field, __('photo_album.errors.'.$e->getMessage()));

            return;
        }

        $this->reset('photoIds', 'email', 'description');
        session()->flash('success', __('photo_album.sent'));
    }

    public function deletePhoto(int $photoId, DeleteUnitAlbumPhotoAction $delete): void
    {
        $this->authorize('send', UnitAlbumPhoto::class);
        $delete->handleForUser($photoId, (int) Tenancy::id(), (int) auth()->id());
    }

    public function deleteRecipient(int $recipientId, DeleteUnitAlbumRecipientAction $delete): void
    {
        $this->authorize('send', UnitAlbumPhoto::class);
        $delete->handleId($recipientId, (int) Tenancy::id(), (int) auth()->id());
    }

    public function useRecipient(int $recipientId, UpdateUnitAlbumRecipientAction $recipients): void
    {
        $this->authorize('viewAny', UnitAlbumPhoto::class);

        $recipient = $recipients->findForUnit($recipientId, (int) Tenancy::id(), (int) $this->unitId);
        if ($recipient === null) {
            return;
        }

        $this->authorize('update', $recipient);
        $this->email = (string) $recipient->email;
        $this->description = (string) ($recipient->description ?? '');
    }

    public function render(ListUnitAlbumAction $list): View
    {
        $this->authorize('viewAny', UnitAlbumPhoto::class);

        $user = auth()->user();
        $album = $list->handle(
            (int) Tenancy::id(),
            $user?->accessibleLocationIds(),
            $this->unitId,
        );

        return view('livewire.pages.photo-album-index', [
            'units' => $album['units'],
            'photos' => $album['photos'],
            'recipients' => $album['recipients'],
            'sends' => $album['sends'],
        ]);
    }
}
