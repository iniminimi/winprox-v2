<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'category_id',
        'bulk_batch_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Unit $unit) {
            // Willekeurige token per unit (niet afgeleid van naam); uniek in de database.
            $unit->qr_token = self::generateUniqueQrToken();
        });
    }

    public static function generateUniqueQrToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (static::withoutGlobalScopes()->where('qr_token', $token)->exists());

        return $token;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bulkBatch(): BelongsTo
    {
        return $this->belongsTo(UnitBulkBatch::class, 'bulk_batch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(InternalTeam::class, 'internal_team_unit', 'unit_id', 'internal_team_id')
            ->withTimestamps();
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

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function qrLinkPhotos(): HasMany
    {
        return $this->hasMany(QrLinkPhoto::class);
    }

    public function hasOpenIssues(): bool
    {
        return $this->issues()
            ->whereIn('status', \App\Enums\TaskStatus::openValues())
            ->exists();
    }
}
