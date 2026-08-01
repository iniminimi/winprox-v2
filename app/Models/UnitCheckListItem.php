<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCheckListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_check_list_id',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(UnitCheckList::class, 'unit_check_list_id');
    }
}
