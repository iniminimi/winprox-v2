<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelcomeVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'visited_at',
        'locale',
        'visitor_hash',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }
}
