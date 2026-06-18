<?php

namespace App\Services\Translation;

use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaProvider implements TranslationProviderInterface
{
    public function translate(string $text, string $targetLanguage): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (! config('ollama.enabled', true)) {
            return $text;
        }

        $targetLabel = LocaleSupport::languageLabel(
            LocaleSupport::normalize($targetLanguage),
        );

        $prompt = 'Translate the following text into '.$targetLabel
            .'. Provide ONLY the translation, no introduction or quotes: '.$text;

        try {
            $response = Http::timeout((int) config('ollama.timeout', 60))
                ->post(config('ollama.url').'/api/generate', [
                    'model' => config('ollama.model', 'llama3.1'),
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('ollama.translation_failed', [
                    'status' => $response->status(),
                    'target' => $targetLanguage,
                ]);

                return $text;
            }

            $translated = trim((string) $response->json('response', ''));

            if ($translated === '') {
                return $text;
            }

            return $this->stripWrappingQuotes($translated);
        } catch (\Throwable $exception) {
            Log::warning('ollama.translation_unreachable', [
                'target' => $targetLanguage,
                'message' => $exception->getMessage(),
            ]);

            return $text;
        }
    }

    private function stripWrappingQuotes(string $text): string
    {
        if (
            (str_starts_with($text, '"') && str_ends_with($text, '"'))
            || (str_starts_with($text, "'") && str_ends_with($text, "'"))
        ) {
            return trim(substr($text, 1, -1));
        }

        return $text;
    }
}
