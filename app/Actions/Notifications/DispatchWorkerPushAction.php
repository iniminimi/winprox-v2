<?php

namespace App\Actions\Notifications;

use App\Jobs\SendWorkerPushJob;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Push\WorkerPushScreen;

class DispatchWorkerPushAction
{
    /**
     * @param  array<string, string>  $replace
     */
    public function handle(
        Tenant $tenant,
        Worker $worker,
        string $titleKey,
        string $bodyKey,
        array $replace,
        string $screen,
        string $tag,
    ): void {
        if ((int) $worker->tenant_id !== (int) $tenant->id || ! $worker->is_active) {
            return;
        }

        $params = ['tenant' => $tenant->id];
        if (WorkerPushScreen::isOpen($screen)) {
            $params['open'] = $screen;
        }

        SendWorkerPushJob::dispatch(
            (int) $tenant->id,
            (int) $worker->id,
            $titleKey,
            $bodyKey,
            $replace,
            route('public.time-portal.home', $params),
            $tag,
        );
    }
}
