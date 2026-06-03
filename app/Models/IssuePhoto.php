<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IssuePhoto extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'issue_id', 'issue_update_id', 'path'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function issueUpdate(): BelongsTo
    {
        return $this->belongsTo(IssueUpdate::class);
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

        // Relatief pad: werkt ongeacht APP_URL (localhost vs LAN op Windows).
        return '/storage/'.str_replace('\\', '/', $this->path);
    }
}
