<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeonamePlace extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'latitude',
        'longitude',
        'country_code',
        'feature_class',
        'feature_code',
    ];

    protected $casts = [
        'id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
