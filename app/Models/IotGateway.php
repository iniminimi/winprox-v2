<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IotGateway extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'token_hash',
        'token_prefix',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(IotSensor::class, 'iot_gateway_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IotEvent::class, 'iot_gateway_id');
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * @return array{gateway: self, plain_token: string}
     */
    public static function issueCredentials(string $name): array
    {
        $plain = 'wpiot_'.Str::lower(Str::random(40));

        $gateway = new self([
            'name' => $name,
            'token_hash' => self::hashToken($plain),
            'token_prefix' => substr($plain, 0, 12),
            'is_active' => true,
        ]);

        return ['gateway' => $gateway, 'plain_token' => $plain];
    }

    public function matchesToken(string $plainToken): bool
    {
        return hash_equals($this->token_hash, self::hashToken($plainToken));
    }
}
