<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BelgianPublicHoliday extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'date',
        'year',
        'code',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'year' => 'integer',
    ];
}
