<?php

declare(strict_types=1);

namespace App\Data\PhotoAlbum;

final readonly class SendUnitAlbumPhotosData
{
    /**
     * @param  list<int>  $photoIds
     */
    public function __construct(
        public int $unitId,
        public array $photoIds,
        public string $email,
        public ?string $recipientDescription = null,
    ) {}

    /**
     * @param  array{unit_id: int|string, photo_ids: array<int, int|string>, email: string, description?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $description = trim((string) ($validated['description'] ?? ''));

        return new self(
            unitId: (int) $validated['unit_id'],
            photoIds: array_values(array_unique(array_map('intval', $validated['photo_ids']))),
            email: trim((string) $validated['email']),
            recipientDescription: $description !== '' ? $description : null,
        );
    }
}
