<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Klant (CRM-relatie: wie communiceert/betaalt). De werkadressen zijn Locations.
 * Bewust kaal — geen facturen, offertes of contracten (docs/CHECKMATE.md §3).
 */
class Customer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_name',
        'email',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function activeLocations(): HasMany
    {
        return $this->hasMany(Location::class)->where('is_active', true);
    }
}
