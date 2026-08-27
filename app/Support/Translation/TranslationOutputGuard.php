<?php

namespace App\Support\Translation;

/**
 * Detects unusable model output that must never be shown as a translation
 * (assistant meta-replies, empty answers, runaway commentary, untranslated echoes).
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

    /**
     * Lightweight content cues that the text is already in the given locale.
     *
     * @var array<string, list<string>>
     */
    private const LOCALE_CUES = [
        'nl' => ['deze', 'niet', 'voor', 'met', 'van', 'een', 'het', 'graag', 'werkt', 'kapot', 'lek', 'lekke', 'kraan', 'venster', 'schoonmaken', 'controleer', 'opmerking', 'afval'],
        'en' => ['the', 'and', 'please', 'with', 'from', 'this', 'that', 'broken', 'leak', 'leaking', 'window', 'clean', 'check', 'printer', 'does', 'not', 'work'],
        'fr' => ['le', 'la', 'les', 'des', 'une', 'pas', 'avec', 'pour', 'dans', 'cassé', 'fuite', 'fenêtre', 'nettoyer', 'vérifier'],
        'de' => ['der', 'die', 'das', 'und', 'nicht', 'mit', 'für', 'kaputt', 'undicht', 'fenster', 'reinigen', 'prüfen', 'bitte'],
        'es' => ['el', 'la', 'los', 'las', 'una', 'con', 'para', 'por', 'roto', 'fuga', 'ventana', 'limpiar', 'verificar'],
        'it' => ['il', 'lo', 'la', 'gli', 'una', 'con', 'per', 'non', 'rotto', 'perdita', 'finestra', 'pulire', 'verificare'],
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

    /**
     * True when the provider echoed the source unchanged while a real translation
     * into another locale was expected.
     */
    public static function isUntranslatedEcho(
        string $output,
        string $source,
        string $targetLocale,
        ?string $sourceLocale = null,
    ): bool {
        $output = trim($output);
        $source = trim($source);

        if ($output === '' || $source === '') {
            return false;
        }

        if (mb_strtolower($output) !== mb_strtolower($source)) {
            return false;
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);
        $sourceLocale = LocaleSupport::normalize($sourceLocale);

        if ($targetLocale === $sourceLocale) {
            return false;
        }

        // Codes / symbols only — nothing linguistic to translate.
        if (! preg_match('/\p{L}{2,}/u', $source)) {
            return false;
        }

        // Already looks like the target language (e.g. English source → French request
        // that happens to keep a brand-like English phrase).
        if (self::appearsToBeLocale($source, $targetLocale)) {
            return false;
        }

        return true;
    }

    public static function appearsToBeLocale(string $text, string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);
        $cues = self::LOCALE_CUES[$locale] ?? [];
        if ($cues === []) {
            return false;
        }

        $lower = mb_strtolower($text);
        $hits = 0;
        foreach ($cues as $cue) {
            if (preg_match('/\b'.preg_quote($cue, '/').'\b/u', $lower)) {
                $hits++;
                if ($hits >= 1 && mb_strlen($text) < 40) {
                    return true;
                }
                if ($hits >= 2) {
                    return true;
                }
            }
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
