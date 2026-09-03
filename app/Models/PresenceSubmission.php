<?php

namespace App\Models;

use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\PresenceSubmissionStatus;
use App\Enums\PresenceType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresenceSubmission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'work_shift_id',
        'work_break_id',
        'clock_point_id',
        'location_id',
        'source_event',
        'presence_type',
        'scope',
        'registration_at',
        'status',
        'rsz_id',
        'rsz_validity',
        'remarks',
        'request_meta',
        'error_message',
        'submitted_at',
    ];

    protected $casts = [
        'source_event' => PresenceSourceEvent::class,
        'presence_type' => PresenceType::class,
        'scope' => PresenceComplianceScope::class,
        'status' => PresenceSubmissionStatus::class,
        'registration_at' => 'datetime',
        'submitted_at' => 'datetime',
        'remarks' => 'array',
        'request_meta' => 'array',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function workBreak(): BelongsTo
    {
        return $this->belongsTo(WorkBreak::class);
    }

    public function clockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
