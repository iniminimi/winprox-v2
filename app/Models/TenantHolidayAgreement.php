<?php

namespace App\Models;

use App\Enums\HolidayAgreementEffect;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bedrijfsbrede feestdagafspraak: één regeling per tenant die voor alle
 * uitvoerders geldt in de maandnorm. De kalender zelf (datums/codes)
 * blijft globaal in belgian_public_holidays.
 */
class TenantHolidayAgreement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'holiday_date',
        'effect',
        'replacement_date',
    ];

    protected $casts = [
        'holiday_date' => 'date:Y-m-d',
        'replacement_date' => 'date:Y-m-d',
        'effect' => HolidayAgreementEffect::class,
    ];

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(BelgianPublicHoliday::class, 'holiday_date', 'date');
    }
}
