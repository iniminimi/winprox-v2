<?php

namespace Tests\Support;

use App\Services\Translation\TranslationProviderInterface;

class FakeTranslationProvider implements TranslationProviderInterface
{
    public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): string
    {
        return '['.$targetLanguage.'] '.$text;
    }
}
