<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\FiltersTeamLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamAnnouncement extends Model
{
    use BelongsToTenant, FiltersTeamLibrary, HasFactory;

    public const LIBRARY_SEARCH_COLUMNS = ['description'];

    protected $fillable = [
        'tenant_id',
        'internal_team_id',
        'worker_id',
        'description',
        'original_language',
        'is_active',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function forWholeTeam(): bool
    {
        return $this->worker_id === null;
    }

    /**
     * @param  Builder<TeamAnnouncement>  $query
     * @return Builder<TeamAnnouncement>
     */
    public function scopeWithLibraryStatus(Builder $query, string $status): Builder
    {
        $isActive = $this->qualifyColumn('is_active');
        $expiresAt = $this->qualifyColumn('expires_at');

        return match ($status) {
            'active' => $query->where($isActive, true)
                ->where(fn (Builder $q) => $q->whereNull($expiresAt)->orWhere($expiresAt, '>=', now())),
            'expired' => $query->where($isActive, true)->where($expiresAt, '<', now()),
            'inactive' => $query->where($isActive, false),
            default => $query,
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(now());
    }

    public function scopeVisibleToWorker(Builder $query, Worker $worker): Builder
    {
        return $query
            ->where('internal_team_id', $worker->internal_team_id)
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn (Builder $q) => $q->whereNull('worker_id')->orWhere('worker_id', $worker->id));
    }
}
