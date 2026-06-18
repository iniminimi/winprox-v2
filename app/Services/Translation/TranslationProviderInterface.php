<?php

namespace App\Services\Translation;

interface TranslationProviderInterface
{
    public function translate(string $text, string $targetLanguage): string;
}
