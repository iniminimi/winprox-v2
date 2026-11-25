<?php

namespace App\Actions\Retention;

use App\Models\AbsenceRequestPhoto;
use App\Models\Tenant;
use App\Models\TenantTimeArchive;
use App\Models\User;
use App\Support\AbsenceRequestPhotoStorage;
use App\Support\Time\AbsencePhotoRetention;
use App\Support\Time\TimeArchiveCsv;
use App\Support\Time\TimeArchiveWindow;
use App\Support\Time\TimeArchiveZip;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Foto's ouder dan 6 maanden gaan in een zip van die maand en verdwijnen daarna
 * bij de aanvraag. De aanvraag zelf blijft. Zonder geslaagde zip wordt niets gewist.
 */
final class PruneAbsenceRequestPhotosAction
{
    public function __construct(private AbsenceRequestPhotoStorage $storage) {}

    /**
     * @return array{scanned: int, removed: int, skipped: int}
     */
    public function handle(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $now = TimeArchiveWindow::brussels($now);
        $cutoff = AbsencePhotoRetention::dropBefore($now);
        $stats = ['scanned' => 0, 'removed' => 0, 'skipped' => 0];
        $groups = [];

        AbsenceRequestPhoto::query()
            ->withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($photos) use (&$groups, &$stats): void {
                foreach ($photos as $photo) {
                    $stats['scanned']++;
                    $period = TimeArchiveWindow::photoPeriod($photo->created_at);
                    $groups[(int) $photo->tenant_id][$period][] = (int) $photo->id;
                }
            });

        if ($dryRun) {
            $stats['removed'] = $stats['scanned'];

            return $stats;
        }

        foreach ($groups as $tenantId => $periods) {
            foreach ($periods as $period => $ids) {
                $result = $this->archiveGroup($tenantId, (string) $period, $ids, $now);
                $stats['removed'] += $result['removed'];
                $stats['skipped'] += $result['skipped'];
            }
        }

        return $stats;
    }

    /**
     * @param  list<int>  $ids
     * @return array{removed: int, skipped: int}
     */
    private function archiveGroup(int $tenantId, string $period, array $ids, CarbonInterface $now): array
    {
        $result = ['removed' => 0, 'skipped' => 0];
        $disk = Storage::disk(AbsenceRequestPhoto::DISK);
        $path = TenantTimeArchive::DIRECTORY.'/'.$tenantId.'/fotos-'.$period.'.zip';
        $fresh = [];
        $files = [];

        $photos = AbsenceRequestPhoto::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->with(['absenceRequest.worker'])
            ->orderBy('id')
            ->get();

        foreach ($photos as $photo) {
            $source = (string) $photo->path;
            if ($source === '' || ! $disk->exists($source)) {
                $result['skipped']++;

                continue;
            }
            $entry = 'fotos/'.$photo->id.'.'.$this->extension($source);
            $bytes = $disk->get($source);
            if (! is_string($bytes) || $bytes === '') {
                $result['skipped']++;

                continue;
            }
            $files[$entry] = $bytes;
            $request = $photo->absenceRequest;
            $fresh[] = [
                (string) $photo->id,
                (string) ($request?->id ?? ''),
                $this->cell($request?->worker?->displayName()),
                $request?->date_from?->toDateString() ?? '',
                $request?->date_to?->toDateString() ?? '',
                $this->cell($request?->kind?->value),
                $entry,
            ];
        }

        if ($fresh === []) {
            return $result;
        }

        $byId = [];
        if ($disk->exists($path)) {
            try {
                foreach (TimeArchiveCsv::decode(TimeArchiveZip::read($path, 'fotos.csv') ?? '') as $row) {
                    $byId[(int) $row[0]] = $row;
                }
            } catch (RuntimeException $exception) {
                report($exception);

                return ['removed' => 0, 'skipped' => count($ids)];
            }
        }
        foreach ($fresh as $row) {
            $byId[(int) $row[0]] = $row;
        }
        ksort($byId);

        $locale = $this->locale($tenantId);
        $entries = $files;
        $entries['fotos.csv'] = TimeArchiveCsv::encode([
            'id',
            (string) __('time.archive.columns.photos.request', [], $locale),
            (string) __('time.archive.columns.photos.worker', [], $locale),
            (string) __('time.archive.columns.photos.date_from', [], $locale),
            (string) __('time.archive.columns.photos.date_to', [], $locale),
            (string) __('time.archive.columns.photos.kind', [], $locale),
            (string) __('time.archive.columns.photos.file', [], $locale),
        ], array_values($byId));

        try {
            TimeArchiveZip::add($path, $entries);
        } catch (RuntimeException $exception) {
            report($exception);

            return ['removed' => 0, 'skipped' => count($ids)];
        }

        $verified = [];
        foreach ($fresh as $row) {
            $photoId = (int) $row[0];
            $entry = (string) $row[6];
            $source = (string) $photos->firstWhere('id', $photoId)?->path;
            $stored = $source !== '' ? $disk->size($source) : 0;
            $zipped = TimeArchiveZip::uncompressedSize($path, $entry);
            if ($zipped === null || $zipped !== $stored || $stored < 1) {
                $result['skipped']++;

                continue;
            }
            $verified[] = $photoId;
        }

        if ($verified === []) {
            return $result;
        }

        $this->remember($tenantId, $period, $path, $now);

        foreach ($photos as $photo) {
            if (! in_array((int) $photo->id, $verified, true)) {
                continue;
            }
            $this->storage->delete((string) $photo->path);
            $photo->delete();
            $result['removed']++;
        }

        return $result;
    }

    private function remember(int $tenantId, string $period, string $path, CarbonInterface $now): void
    {
        $row = TenantTimeArchive::query()->withoutGlobalScopes()->firstOrNew([
            'tenant_id' => $tenantId,
            'kind' => TenantTimeArchive::KIND_PHOTOS,
            'period_key' => $period,
        ]);
        $row->path = $path;
        $row->bytes = (int) Storage::disk(TenantTimeArchive::DISK)->size($path);
        if (! $row->exists) {
            $row->available_until = TimeArchiveWindow::photoAvailableUntil($period, $now);
        }
        $row->save();
    }

    private function locale(int $tenantId): string
    {
        $locale = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('locale');

        return is_string($locale) && $locale !== '' ? $locale : (string) config('app.locale', 'nl');
    }

    private function extension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }

    private function cell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            return "'".$text;
        }

        return $text;
    }
}
