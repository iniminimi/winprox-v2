<?php

namespace App\Support\Translation;

use App\Enums\TranslationSyncPhase;
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
}
