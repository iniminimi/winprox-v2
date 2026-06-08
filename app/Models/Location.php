<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Location $location) {
            if (empty($location->country_code)) {
                $location->country_code = 'BE';
            }
        });
    }

    public function formattedAddress(): string
    {
        $parts = array_filter([
            trim(trim((string) $this->street).' '.trim((string) $this->house_number)),
            trim(trim((string) $this->postal_code).' '.trim((string) $this->city)),
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return (string) ($this->address ?? '');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function bulkBatches(): HasMany
    {
        return $this->hasMany(UnitBulkBatch::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }
}
