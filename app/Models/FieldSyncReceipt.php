<?php

namespace App\Models;

use App\Enums\FieldSyncType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldSyncReceipt extends Model
{
    use BelongsToTenant;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'client_id',
        'type',
        'unit_id',
        'status',
        'http_status',
        'result',
    ];

    protected $casts = [
        'type' => FieldSyncType::class,
        'result' => 'array',
        'http_status' => 'integer',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function wasSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
