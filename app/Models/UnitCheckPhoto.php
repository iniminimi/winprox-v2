<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UnitCheckPhoto extends Model
{
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'unit_check_id', 'path'];

    public function unitCheck(): BelongsTo
    {
        return $this->belongsTo(UnitCheck::class);
    }

    public function hasPublicFile(): bool
    {
        return is_string($this->path)
            && trim($this->path) !== ''
            && Storage::disk('public')->exists($this->path);
    }

    public function publicUrl(): ?string
    {
        if (! $this->hasPublicFile()) {
            return null;
        }

        return '/storage/'.str_replace('\\', '/', $this->path);
    }
}
