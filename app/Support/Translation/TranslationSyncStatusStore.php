<?php

namespace App\Support\Translation;

use App\Enums\TranslationSyncPhase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

final class TranslationSyncStatusStore
{
    public function read(): ?array
    {
        $path = (string) config('translation_sync.status_path');

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function write(TranslationSyncPhase $phase, ?int $actorUserId = null, array $extra = []): void
    {
        $payload = array_merge([
            'phase' => $phase->value,
            'actor_user_id' => $actorUserId,
            'updated_at' => now()->toIso8601String(),
        ], $extra);

        Storage::disk('local')->put(
            (string) config('translation_sync.status_path'),
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    public function clear(): void
    {
        $path = (string) config('translation_sync.status_path');

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function isStale(?array $status): bool
    {
        if ($status === null) {
            return false;
        }

        $phase = TranslationSyncPhase::tryFrom((string) ($status['phase'] ?? ''));
        if ($phase === null || ! $phase->isActive()) {
            return false;
        }

        $updatedAt = $status['updated_at'] ?? null;
        if (! is_string($updatedAt) || $updatedAt === '') {
            return true;
        }

        $threshold = (int) config('translation_sync.stale_after_seconds', 1200);

        return CarbonImmutable::parse($updatedAt)
            ->addSeconds($threshold)
            ->isPast();
    }
}
