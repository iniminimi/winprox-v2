<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'default_internal_team_id',
        'bulk_batch_id',
        'name',
        'qr_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Unit $unit) {
            if (empty($unit->qr_token)) {
                $unit->qr_token = Str::lower(Str::random(40));
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bulkBatch(): BelongsTo
    {
        return $this->belongsTo(UnitBulkBatch::class, 'bulk_batch_id');
    }

    public function defaultInternalTeam(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'default_internal_team_id');
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

    public function hasOpenIssues(): bool
    {
        return $this->issues()
            ->whereIn('status', \App\Enums\TaskStatus::openValues())
            ->exists();
    }
}
