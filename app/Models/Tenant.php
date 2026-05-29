<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function internalTeams(): HasMany
    {
        return $this->hasMany(InternalTeam::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
