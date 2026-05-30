<?php

namespace App\Actions\Retention;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\IssuePhoto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Verwijdert foto-bestanden van oude gesloten meldingen; meldingen blijven bestaan.
 */
final class PruneClosedIssueMediaAction
{
    /**
     * @return array{issues_scanned: int, photos_removed: int}
     */
    public function handle(bool $dryRun = false, ?Carbon $now = null): array
    {
        $now ??= now();
        $cutoff = $now->copy()->subDays((int) config('data_retention.closed_issue_media_days', 365));

        $stats = ['issues_scanned' => 0, 'photos_removed' => 0];

        Issue::query()
            ->withoutGlobalScopes()
            ->where('status', TaskStatus::Closed)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($issues) use ($dryRun, &$stats): void {
                foreach ($issues as $issue) {
                    $stats['issues_scanned']++;

                    $photos = IssuePhoto::query()
                        ->withoutGlobalScopes()
                        ->where('issue_id', $issue->id)
                        ->get();

                    foreach ($photos as $photo) {
                        if (! $dryRun) {
                            Storage::disk('public')->delete($photo->path);
                            $photo->delete();
                        }
                        $stats['photos_removed']++;
                    }
                }
            });

        return $stats;
    }
}
