<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;

class GenerateQrStickerNumberAction
{
    public function handle(): string
    {
        $prefix = date('ym');

        do {
            $suffix = $this->nextSuffixForPrefix($prefix);
            $canonical = $prefix.'-'.str_pad((string) $suffix, 5, '0', STR_PAD_LEFT);
        } while (QrCode::withoutGlobalScopes()->where('sticker_number', $canonical)->exists());

        return $canonical;
    }

    private function nextSuffixForPrefix(string $prefix): int
    {
        $max = 0;

        $existing = QrCode::withoutGlobalScopes()
            ->where('sticker_number', 'like', $prefix.'-%')
            ->pluck('sticker_number');

        $pattern = '/^'.preg_quote($prefix, '/').'-(\d{5})$/';

        foreach ($existing as $stickerNumber) {
            if (preg_match($pattern, (string) $stickerNumber, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        if ($next > 99_999) {
            throw new \RuntimeException("Sticker number capacity exceeded for prefix {$prefix}.");
        }

        return $next;
    }
}
