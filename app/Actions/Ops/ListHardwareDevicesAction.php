<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Enums\HardwareDeviceStatus;
use App\Enums\HardwareDeviceType;
use App\Models\HardwareDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListHardwareDevicesAction
{
    /**
     * @return LengthAwarePaginator<int, HardwareDevice>
     */
    public function handle(
        string $search = '',
        ?HardwareDeviceStatus $status = null,
        ?HardwareDeviceType $type = null,
        ?int $tenantId = null,
        bool $withoutTenant = false,
        bool $withoutMac = false,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = HardwareDevice::query()
            ->with(['tenant:id,name', 'clockPoint:id,name,tenant_id'])
            ->orderByDesc('sn_scanned_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('serial_number', 'like', $like)
                    ->orWhere('mac', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('purchase_ref', 'like', $like);
            });
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        if ($withoutTenant) {
            $query->whereNull('tenant_id');
        } elseif ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($withoutMac) {
            $query->whereNull('mac');
        }

        return $query->paginate($perPage);
    }
}
