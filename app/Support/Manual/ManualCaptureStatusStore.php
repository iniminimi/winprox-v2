<?php

namespace App\Support\Manual;

use App\Enums\ManualCaptureRunStatus;
use Illuminate\Support\Facades\Storage;

/**
 * Leest/schrijft de laatste handleiding-screenshot-run (bestand, geen DB).
 */
final class ManualCaptureStatusStore
{
    public function read(): ?array
    {
        $path = (string) config('manual_capture.status_path');

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function write(ManualCaptureRunStatus $status, ?int $actorUserId = null, array $extra = []): void
    {
        $payload = array_merge([
            'status' => $status->value,
            'actor_user_id' => $actorUserId,
            'updated_at' => now()->toIso8601String(),
        ], $extra);

        Storage::disk('local')->put(
            (string) config('manual_capture.status_path'),
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }
}
