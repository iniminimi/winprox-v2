<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitCheckListTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCheckListTranslation extends Model
{
    protected $fillable = [
        'unit_check_list_id',
        'locale',
        'name',
        'items',
        'status',
    ];

    protected $casts = [
        'items' => 'array',
        'status' => UnitCheckListTranslationStatus::class,
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(UnitCheckList::class, 'unit_check_list_id');
    }
}
