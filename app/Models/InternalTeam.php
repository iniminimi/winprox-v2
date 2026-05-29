<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InternalTeam extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'sort_order', 'field_qr_token', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (InternalTeam $team) {
            if (empty($team->field_qr_token)) {
                $team->field_qr_token = Str::lower(Str::random(40));
            }
        });
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function activeWorkerCount(): int
    {
        return (int) $this->workers()->where('is_active', true)->count();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
