<?php

namespace App\Models;

use App\Enums\UiTheme;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        'locale',
        'notify_on_new_issue_email',
        'ui_theme',
        'dashboard_intent_order',
        'dashboard_hidden_tiles',
        'dashboard_hidden_kpis',
        'dashboard_hidden_checkmate_tiles',
        'password',
        'is_superuser',
        'is_active',
        'role',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_user')->withTimestamps();
    }

    /**
     * Een medewerker met gekozen locaties ziet alleen collega’s van die locaties.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        $allowed = $viewer->accessibleLocationIds();

        if ($allowed === null) {
            return $query;
        }

        return $query->whereHas(
            'locations',
            fn (Builder $locations) => $locations->whereIn('locations.id', $allowed),
        );
    }

    public function isVisibleTo(User $viewer): bool
    {
        $allowed = $viewer->accessibleLocationIds();

        if ($allowed === null) {
            return true;
        }

        $ids = $this->relationLoaded('locations')
            ? $this->locations->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $this->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();

        return array_intersect($ids, $allowed) !== [];
    }

    public function linkedWorker(): HasOne
    {
        return $this->hasOne(Worker::class);
    }

    /** Admin en superuser: alle locaties. */
    public function hasUnrestrictedLocationAccess(): bool
    {
        return $this->is_superuser || $this->isAdmin();
    }

    /** null = alle locaties; anders expliciete id-lijst (leeg = geen toegang). */
    public function accessibleLocationIds(): ?array
    {
        if ($this->hasUnrestrictedLocationAccess()) {
            return null;
        }

        $ids = $this->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();

        // Lege pivot = alle locaties (bestaande tenants).
        if ($ids === []) {
            return null;
        }

        return $ids;
    }

    public function canAccessLocation(int $locationId): bool
    {
        $allowed = $this->accessibleLocationIds();
        if ($allowed === null) {
            return true;
        }

        return in_array($locationId, $allowed, true);
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
        'email_verify_token',
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
            'email_verify_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_superuser' => 'boolean',
            'is_active' => 'boolean',
            'notify_on_new_issue_email' => 'boolean',
            'role' => 'string',
            'ui_theme' => 'string',
            'dashboard_intent_order' => 'array',
            'dashboard_hidden_tiles' => 'array',
            'dashboard_hidden_kpis' => 'array',
            'dashboard_hidden_checkmate_tiles' => 'array',
            'whats_new_dismissed_on' => 'date',
        ];
    }

    public function uiThemeEnum(): UiTheme
    {
        return UiTheme::tryFromString($this->ui_theme);
    }
}
