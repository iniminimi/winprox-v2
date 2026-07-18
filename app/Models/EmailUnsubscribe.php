<?php

namespace App\Models;

use App\Enums\EmailUnsubscribeSource;
use Illuminate\Database\Eloquent\Model;

class EmailUnsubscribe extends Model
{
    protected $fillable = [
        'email',
        'source',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => EmailUnsubscribeSource::class,
            'unsubscribed_at' => 'datetime',
        ];
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function isUnsubscribed(string $email): bool
    {
        return static::query()
            ->where('email', static::normalizeEmail($email))
            ->exists();
    }
}
