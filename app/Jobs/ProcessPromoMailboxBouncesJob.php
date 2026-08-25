<?php

namespace App\Jobs;

use App\Actions\Marketing\ProcessPromoMailboxBouncesAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessPromoMailboxBouncesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const CACHE_KEY = 'promo.mailbox_bounce_scan';

    public int $timeout = 50;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function uniqueId(): string
    {
        return 'promo-mailbox-bounces';
    }

    /**
     * @param  array{status: string, result?: array<string, mixed>, error?: string, at?: string}  $payload
     */
    public static function remember(array $payload): void
    {
        Cache::put(self::CACHE_KEY, $payload, now()->addMinutes(10));
    }

    /**
     * @return array{status: string, result?: array<string, mixed>, error?: string, at?: string}|null
     */
    public static function status(): ?array
    {
        $payload = Cache::get(self::CACHE_KEY);

        return is_array($payload) ? $payload : null;
    }

    public static function forgetStatus(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function handle(ProcessPromoMailboxBouncesAction $process): void
    {
        self::remember([
            'status' => 'running',
            'at' => now()->toIso8601String(),
        ]);

        try {
            $result = $process->handle(
                unseenOnly: false,
                limit: ProcessPromoMailboxBouncesAction::DEFAULT_MANUAL_LIMIT,
                dryRun: false,
                sinceDays: ProcessPromoMailboxBouncesAction::DEFAULT_SINCE_DAYS,
            );
        } catch (Throwable $exception) {
            self::remember([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'at' => now()->toIso8601String(),
            ]);
            report($exception);

            return;
        }

        self::remember([
            'status' => 'done',
            'result' => $result,
            'at' => now()->toIso8601String(),
        ]);
    }
}
