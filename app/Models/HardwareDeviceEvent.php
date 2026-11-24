<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HardwareDeviceEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HardwareDeviceEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'hardware_device_id',
        'event_type',
        'tenant_id',
        'clock_point_id',
        'actor_user_id',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => HardwareDeviceEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(HardwareDevice::class, 'hardware_device_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function clockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
