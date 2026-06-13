<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Unit extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'category_id',
        'bulk_batch_id',
        'import_batch_id',
        'name',
        'description',
        'is_active',
        'background_photo_path',
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

    public function gpsReports(): HasMany
    {
        return $this->hasMany(UnitGpsReport::class);
    }

    public function latestGpsReport(): HasOne
    {
        return $this->hasOne(UnitGpsReport::class)->latestOfMany('reported_at');
    }

    public function backgroundPhotoPublicUrl(): ?string
    {
        $path = $this->background_photo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function hasOpenIssues(): bool
    {
        return $this->issues()
            ->whereIn('status', \App\Enums\TaskStatus::openValues())
            ->exists();
    }

    public function hasGps(): bool
    {
        if ($this->relationLoaded('latestGpsReport')) {
            return $this->latestGpsReport !== null;
        }

        if (isset($this->gps_reports_exists)) {
            return (bool) $this->gps_reports_exists;
        }

        return $this->gpsReports()->exists();
    }

    public function googleMapsUrl(): ?string
    {
        $report = $this->relationLoaded('latestGpsReport')
            ? $this->latestGpsReport
            : $this->latestGpsReport()->first();

        return $report?->googleMapsUrl();
    }
}
