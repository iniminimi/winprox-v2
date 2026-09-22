<?php

namespace App\Models;

use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceRequest extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'kind',
        'shift_type_id',
        'date_from',
        'date_to',
        'description',
        'status',
        'decision_description',
        'decided_by_user_id',
        'decided_at',
        'replaced_shifts',
    ];

    protected $casts = [
        'kind' => ShiftTypeKind::class,
        'date_from' => 'date',
        'date_to' => 'date',
        'status' => AbsenceRequestStatus::class,
        'decided_at' => 'datetime',
        'replaced_shifts' => 'array',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function workerMayWithdraw(): bool
    {
        if ($this->status === AbsenceRequestStatus::Pending) {
            return true;
        }

        return $this->status === AbsenceRequestStatus::Approved
            && $this->date_from->toDateString() >= now()->toDateString();
    }
}
