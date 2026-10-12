<?php

namespace App\Models;

use App\Enums\HolidayAgreementEffect;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerHolidayAgreement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'holiday_date',
        'effect',
        'replacement_date',
    ];

    protected $casts = [
        'holiday_date' => 'date:Y-m-d',
        'replacement_date' => 'date:Y-m-d',
        'effect' => HolidayAgreementEffect::class,
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(BelgianPublicHoliday::class, 'holiday_date', 'date');
    }
}
