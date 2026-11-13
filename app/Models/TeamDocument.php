<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\FiltersTeamLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TeamDocument extends Model
{
    use BelongsToTenant, FiltersTeamLibrary, HasFactory;

    public const DISK = 'local';

    public const LIBRARY_SEARCH_COLUMNS = ['title', 'description'];

    protected $fillable = [
        'tenant_id',
        'internal_team_id',
        'worker_id',
        'title',
        'description',
        'original_language',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
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

    public function deleteFile(): void
    {
        if ($this->file_path !== '') {
            Storage::disk(self::DISK)->delete($this->file_path);
        }
    }

    /**
     * @param  Builder<TeamDocument>  $query
     * @return Builder<TeamDocument>
     */
    public function scopeWithLibraryStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active' => $query->where($this->qualifyColumn('is_active'), true),
            'inactive' => $query->where($this->qualifyColumn('is_active'), false),
            default => $query,
        };
    }

    public function scopeVisibleToWorker(Builder $query, Worker $worker): Builder
    {
        return $query
            ->where('internal_team_id', $worker->internal_team_id)
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('worker_id')->orWhere('worker_id', $worker->id));
    }
}
