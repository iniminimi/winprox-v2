<?php

namespace App\Jobs;

use App\Actions\Notifications\SendWorkerPushAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendWorkerPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, string>  $replace
     */
    public function __construct(
        public int $tenantId,
        public int $workerId,
        public string $titleKey,
        public string $bodyKey,
        public array $replace,
        public string $url,
        public string $tag,
    ) {}

    public function handle(SendWorkerPushAction $send): void
    {
        $result = $send->handle(
            $this->tenantId,
            $this->workerId,
            $this->titleKey,
            $this->bodyKey,
            $this->replace,
            $this->url,
            $this->tag,
        );

        if ($result !== SendWorkerPushAction::FAILED || config('queue.default') === 'sync') {
            return;
        }

        throw new RuntimeException('worker_push_failed');
    }
}
