<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailActionToken extends Model
{
    protected $fillable = [
        'email',
        'token',
        'purpose',
    ];
}
