<?php

namespace App\Models;

use App\Enums\CategoryTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    protected $fillable = [
        'category_id',
        'locale',
        'name',
        'status',
    ];

    protected $casts = [
        'status' => CategoryTranslationStatus::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
