<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OpsPortal extends Model
{
    protected $fillable = [
        'token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function issueToken(): string
    {
        return Str::lower(Str::random(40));
    }

    public function portalUrl(): string
    {
        return route('public.ops');
    }

    public function tokenUrl(): string
    {
        return route('public.ops-portal', $this->token);
    }
}
