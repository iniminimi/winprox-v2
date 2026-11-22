<?php

declare(strict_types=1);

namespace App\Actions\ReleaseNotes;

use App\Data\ReleaseNotes\ReleaseNoteData;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;

class ListPublishedReleaseNotesAction
{
    /** @var array<string, string> */
    private const CATEGORIES = [
        'Nieuw' => 'new',
        'Verbeterd' => 'improved',
        'Opgelost' => 'fixed',
        'Belangrijk' => 'important',
    ];

    /** @return list<ReleaseNoteData> */
    public function handle(?string $directory = null): array
    {
        $directory ??= base_path('docs/release-notes/published');
        if (! File::isDirectory($directory)) {
            return [];
        }

        $notes = [];
        foreach (File::files($directory) as $file) {
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})\.md$/', $file->getFilename(), $match)) {
                continue;
            }

            $note = $this->parse(File::get($file->getPathname()), $match[1]);
            if ($note->sections !== []) {
                $notes[] = $note;
            }
        }

        usort(
            $notes,
            fn (ReleaseNoteData $a, ReleaseNoteData $b): int => $b->date->getTimestamp() <=> $a->date->getTimestamp(),
        );

        return $notes;
    }

    public function latest(?string $directory = null): ?ReleaseNoteData
    {
        return $this->handle($directory)[0] ?? null;
    }

    /** @return list<ReleaseNoteData> */
    public function recent(?string $directory = null, ?CarbonInterface $now = null): array
    {
        return array_values(array_filter(
            $this->handle($directory),
            fn (ReleaseNoteData $note): bool => $this->isInLastWeek($note->date, $now),
        ));
    }

    public function isInLastWeek(CarbonInterface $date, ?CarbonInterface $now = null): bool
    {
        $zone = (string) config('app.timezone');
        $today = ($now ?? now())->timezone($zone)->startOfDay();
        $noteDay = $date->copy()->timezone($zone)->startOfDay();

        return ! $noteDay->lt($today->copy()->subDays(6));
    }

    public function parse(string $markdown, string $date): ReleaseNoteData
    {
        $sections = [];
        $current = null;

        foreach (preg_split("/\r\n|\n|\r/", $markdown) ?: [] as $line) {
            if (preg_match('/^##\s+(Nieuw|Verbeterd|Opgelost|Belangrijk)\s*$/u', $line, $match) === 1) {
                $current = self::CATEGORIES[$match[1]];
                $sections[$current] ??= [];

                continue;
            }

            if ($current !== null && preg_match('/^\s*-\s+(.+\S)\s*$/u', $line, $match) === 1) {
                $sections[$current][] = $match[1];
            }
        }

        $ordered = [];
        foreach (self::CATEGORIES as $key) {
            if (! empty($sections[$key])) {
                $ordered[$key] = $sections[$key];
            }
        }

        return new ReleaseNoteData(
            date: CarbonImmutable::parse($date)->startOfDay(),
            sections: $ordered,
        );
    }
}
