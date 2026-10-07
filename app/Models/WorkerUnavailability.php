<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Structurele, terugkerende onbeschikbaarheid van een uitvoerder op een weekdag.
 * weekday: 1 = maandag … 7 = zondag (ISO-8601, zoals Carbon::dayOfWeekIso).
 * Dit is een zachte beperking: de planner krijgt een waarschuwing maar kan toch plannen.
 */
class WorkerUnavailability extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'weekday',
    ];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
