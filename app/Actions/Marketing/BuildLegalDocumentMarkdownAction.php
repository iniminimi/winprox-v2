<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Support\Marketing\HtmlToMarkdown;
use Illuminate\Support\Carbon;

/**
 * Zet een publieke juridische pagina (Blade) om naar Markdown voor AI-crawlers.
 */
class BuildLegalDocumentMarkdownAction
{
    public function handle(string $doc, string $locale): ?string
    {
        $documents = config('legal.documents', []);
        if (! array_key_exists($doc, $documents) || ! is_array($documents[$doc])) {
            return null;
        }

        $supported = config('locales.supported', ['en']);
        $viewLocale = in_array($locale, $supported, true) ? $locale : 'en';

        $previousLocale = app()->getLocale();
        app()->setLocale($viewLocale);
        \Illuminate\Support\Facades\URL::defaults(['locale' => $viewLocale]);

        try {
            $bodyHtml = view()->first([
                "legal.content.{$viewLocale}.{$doc}",
                "legal.content.en.{$doc}",
            ], [])->render();
        } finally {
            app()->setLocale($previousLocale);
            \Illuminate\Support\Facades\URL::defaults(['locale' => $previousLocale]);
        }

        $body = HtmlToMarkdown::convert($bodyHtml);

        $updatedRaw = config('legal.documents_last_updated', '2026-05-10');
        $updatedAt = Carbon::parse($updatedRaw)->format('d/m/Y');
        $title = (string) __($documents[$doc]['label_key'], [], $locale);

        $lines = [
            '# '.$title,
            '',
            __('legal.last_updated', ['date' => $updatedAt], $locale),
            '',
            __('legal.applicable_law_notice', [], $locale),
            '',
            $body,
        ];

        return trim(implode("\n", $lines))."\n";
    }
}
