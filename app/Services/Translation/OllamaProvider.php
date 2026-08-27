<?php

namespace App\Services\Translation;

use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaProvider implements TranslationProviderInterface
{
    public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (! config('ollama.enabled', true)) {
            return $text;
        }

        $targetLocale = LocaleSupport::normalize($targetLanguage);
        $sourceLocale = LocaleSupport::normalize($sourceLanguage);
        $targetLabel = LocaleSupport::languageLabel($targetLocale);
        $sourceLabel = LocaleSupport::languageLabel($sourceLocale);

        $prompt = "You are a professional translator.\n"
            ."Translate the text inside <text></text> from {$sourceLabel} to {$targetLabel}.\n"
            ."Reply with ONLY the {$targetLabel} translation.\n"
            ."No quotes, no preface, no explanation, no questions.\n"
            ."Do not copy the {$sourceLabel} wording unless a proper noun or code must stay unchanged.\n"
            .'<text>'.$text.'</text>';

        try {
            $response = Http::timeout((int) config('ollama.timeout', 60))
                ->post(config('ollama.url').'/api/generate', [
                    'model' => config('ollama.model', 'llama3.1'),
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('ollama.translation_failed', [
                    'status' => $response->status(),
                    'target' => $targetLocale,
                    'source' => $sourceLocale,
                ]);

                return '';
            }

            $translated = trim((string) $response->json('response', ''));
            $translated = $this->stripWrappingQuotes($translated);
            $translated = $this->stripTranslationLabelPrefix($translated, $targetLabel);

            if (
                $translated === ''
                || TranslationOutputGuard::isUnusable($translated, $text)
                || TranslationOutputGuard::isUntranslatedEcho($translated, $text, $targetLocale, $sourceLocale)
            ) {
                Log::warning('ollama.translation_unusable', [
                    'target' => $targetLocale,
                    'source' => $sourceLocale,
                    'source_len' => mb_strlen($text),
                    'output_len' => mb_strlen($translated),
                ]);

                return '';
            }

            return $translated;
        } catch (\Throwable $exception) {
            Log::warning('ollama.translation_unreachable', [
                'target' => $targetLocale,
                'source' => $sourceLocale,
                'message' => $exception->getMessage(),
            ]);

            return '';
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

    private function stripTranslationLabelPrefix(string $text, string $targetLabel): string
    {
        $patterns = [
            '/^'.$targetLabel.'\s*translation\s*:\s*/iu',
            '/^translation\s*:\s*/iu',
            '/^translated\s*text\s*:\s*/iu',
        ];

        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, '', $text);
            if (is_string($stripped) && trim($stripped) !== '') {
                $text = trim($stripped);
            }
        }

        return $text;
    }
}
