<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SensePoint extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'screen_id',
        'token_hash',
        'token_prefix',
        'temperature_low',
        'temperature_target',
        'temperature_high',
        'humidity_low',
        'humidity_target',
        'humidity_high',
        'co2_high',
        'voc_high',
        'season_applied',
        'last_temperature',
        'last_humidity',
        'last_co2',
        'last_voc',
        'last_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature_low' => 'float',
            'temperature_target' => 'float',
            'temperature_high' => 'float',
            'humidity_low' => 'float',
            'humidity_target' => 'float',
            'humidity_high' => 'float',
            'co2_high' => 'integer',
            'voc_high' => 'integer',
            'last_temperature' => 'float',
            'last_humidity' => 'float',
            'last_co2' => 'integer',
            'last_voc' => 'integer',
            'last_recorded_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(SenseReading::class);
    }

    /**
     * @return array{plain: string, hash: string, prefix: string}
     */
    public static function issueToken(): array
    {
        $plain = 'wpsense_'.Str::lower(Str::random(40));

        return [
            'plain' => $plain,
            'hash' => hash('sha256', $plain),
            'prefix' => substr($plain, 0, 16),
        ];
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function matchesToken(string $plainToken): bool
    {
        return hash_equals($this->token_hash, self::hashToken($plainToken));
    }
}
