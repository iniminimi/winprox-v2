<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\PhotoAlbum\DeliverUnitAlbumSendAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Verstuurt één albummail op het geplande tijdstip (uurlimieten: zie
 * SendUnitAlbumPhotosAction). Mutaties gebeuren in de Action.
 */
class SendUnitAlbumMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $unitAlbumSendId) {}

    public function handle(DeliverUnitAlbumSendAction $deliver): void
    {
        $deliver->handle($this->unitAlbumSendId);
    }
}
