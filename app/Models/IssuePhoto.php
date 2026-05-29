<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
