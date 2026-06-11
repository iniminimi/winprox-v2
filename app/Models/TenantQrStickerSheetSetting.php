<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TenantQrStickerSheetSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'template',
        'header_text',
        'background_path',
        'layout_config',
    ];

    protected function casts(): array
    {
        return [
            'layout_config' => 'array',
        ];
    }

    public function templateEnum(): ?QrStickerSheetTemplate
    {
        return QrStickerSheetTemplate::tryFrom((string) $this->template);
    }

    public function backgroundAbsolutePath(): ?string
    {
        $path = $this->background_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }
}
