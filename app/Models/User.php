<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EMPLOYEE = 'employee';

    /** @var list<string> */
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_EMPLOYEE];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'is_superuser',
        'is_active',
        'role',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Beheerder: mag accounts, bedrijfsgegevens, teams aanmaken/deactiveren, billing. */
    public function isAdmin(): bool
    {
        return ! $this->is_superuser && $this->role === self::ROLE_ADMIN;
    }

    /** Medewerker: operationeel + teams/workers-inhoud beheren (geen accountbeheer). */
    public function isEmployee(): bool
    {
        return ! $this->is_superuser && $this->role === self::ROLE_EMPLOYEE;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superuser' => 'boolean',
            'is_active' => 'boolean',
            'role' => 'string',
        ];
    }
}
