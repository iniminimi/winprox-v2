<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    public const AVAILABLE_EVENTS = [
        'issue.created',
        'issue.approved',
        'issue.status_changed',
        'task.created',
        'task.started',
        'task.completed',
    ];

    protected $fillable = [
        'tenant_id',
        'url',
        'secret',
        'events',
        'description',
        'is_active',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookEndpoint $endpoint) {
            if (empty($endpoint->secret)) {
                $endpoint->secret = Str::random(40);
            }
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];

        return in_array($event, $events, true);
    }
}
