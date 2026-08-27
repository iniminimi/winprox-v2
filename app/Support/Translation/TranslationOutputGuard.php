<?php

namespace App\Support\Translation;

/**
 * Detects unusable model output that must never be shown as a translation
 * (assistant meta-replies, empty answers, runaway commentary).
 */
final class TranslationOutputGuard
{
    /**
     * @var list<string>
     */
    private const META_NEEDLES = [
        "i don't see any text",
        'i do not see any text',
        'please provide the text',
        'please provide text',
        'text you\'d like me to translate',
        'text you would like me to translate',
        'what would you like me to translate',
        'no text to translate',
        'there is no text',
        'nothing to translate',
        'as an ai',
        'as a language model',
        "i'm sorry, but",
        'i am sorry, but',
        'cannot translate',
        "can't translate",
        'unable to translate',
        'provide the text you',
        'i need the text',
        'give me the text',
    ];

    public static function isUnusable(string $output, ?string $source = null): bool
    {
        $output = trim($output);

        if ($output === '') {
            return true;
        }

        $lower = mb_strtolower($output);

        foreach (self::META_NEEDLES as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        if ($source === null) {
            return false;
        }

        $source = trim($source);
        if ($source === '') {
            return false;
        }

        // Short labels must not balloon into paragraphs of model chatter.
        if (mb_strlen($source) <= 80 && mb_strlen($output) > max(120, mb_strlen($source) * 5)) {
            return true;
        }

        return false;
    }

    public static function usableOrNull(string $output, ?string $source = null): ?string
    {
        $output = trim($output);

        if (self::isUnusable($output, $source)) {
            return null;
        }

        return $output;
    }
}
