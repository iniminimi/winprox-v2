<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Models\OpsPortal;
use Illuminate\Support\Facades\DB;

final class EnsureOpsPortalAction
{
    public function handle(): OpsPortal
    {
        $existing = OpsPortal::query()->where('is_active', true)->orderBy('id')->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () {
            $locked = OpsPortal::query()->where('is_active', true)->lockForUpdate()->orderBy('id')->first();
            if ($locked !== null) {
                return $locked;
            }

            return OpsPortal::query()->create([
                'token' => OpsPortal::issueToken(),
                'is_active' => true,
            ]);
        });
    }
}
