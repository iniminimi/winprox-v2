<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailUnsubscribe extends Model
{
    protected $fillable = [
        'email',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
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
