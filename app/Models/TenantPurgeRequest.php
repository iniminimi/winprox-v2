<?php

namespace App\Models;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPurgeRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_name',
        'track',
        'status',
        'initiated_by_user_id',
        'export_acknowledged_at',
        'password_verified_at',
        'email_confirmed_at',
        'email_confirmed_by_user_id',
        'confirmation_token_hash',
        'scheduled_purge_at',
        'reminder_sent_at',
        'executed_at',
        'executed_by_user_id',
        'backup_path',
        'backup_expires_at',
        'deleted_counts',
    ];

    protected function casts(): array
    {
        return [
            'track' => TenantPurgeTrack::class,
            'status' => TenantPurgeStatus::class,
            'export_acknowledged_at' => 'datetime',
            'password_verified_at' => 'datetime',
            'email_confirmed_at' => 'datetime',
            'scheduled_purge_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'executed_at' => 'datetime',
            'backup_expires_at' => 'datetime',
            'deleted_counts' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function emailConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email_confirmed_by_user_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            TenantPurgeStatus::AwaitingEmail,
            TenantPurgeStatus::Ready,
            TenantPurgeStatus::Scheduled,
        ], true);
    }

    public function daysUntilPurge(?\DateTimeInterface $now = null): ?int
    {
        if ($this->scheduled_purge_at === null) {
            return null;
        }

        $now = \Illuminate\Support\Carbon::parse($now ?? now());

        return (int) max(0, $now->diffInDays($this->scheduled_purge_at, false));
    }
}
