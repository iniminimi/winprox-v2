<?php

namespace App\Actions\Time;

use App\Models\Worker;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class ConfirmWorkerClockPinAction
{
    public function handle(Worker $worker, string $pin, int $tenantId): ?Worker
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $hash = (string) $worker->clock_pin_hash;
        if ($hash === '') {
            return null;
        }

        return Hash::check(trim($pin), $hash) ? $worker : null;
    }
}
