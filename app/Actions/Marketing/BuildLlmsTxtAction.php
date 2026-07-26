<?php

namespace App\Actions\Marketing;

use App\Support\Faq\FaqSections;

/**
 * Bouwt /llms.txt (Markdown) zodat AI-bots WinProx gericht kunnen indexeren.
 */
class BuildLlmsTxtAction
{
    public function handle(): string
    {
        $lines = [
            '# WinProx',
            '',
            '> Facility management via QR portals: report issues, assign tasks to teams, clock presence (Time), and optionally track ESG — in the browser, without a native app.',
            '',
            'WinProx is a multi-tenant facility platform. Core flow: **issue → tasks → completion**. Citizens and on-site workers use public QR portals; admins and staff use the authenticated workspace. Languages: Dutch, English, French, German, Spanish, Italian.',
            '',
            '## Product pages',
        ];

        foreach (config('locales.supported', []) as $locale) {
            $label = strtoupper((string) $locale);
            $home = route('welcome', ['locale' => $locale], absolute: true);
            $faq = $home.'#faq';
            $lines[] = "- [Homepage ({$label})]({$home}): Marketing overview of Facility, Time and ESG.";
            $lines[] = "- [FAQ on homepage ({$label})]({$faq}): Full frequently asked questions, always visible in HTML for readers and crawlers.";
        }

        $lines[] = '';
        $lines[] = '## Pricing & contact';

        foreach (config('locales.supported', []) as $locale) {
            $label = strtoupper((string) $locale);
            $lines[] = '- [Pricing ('.$label.')]('.route('pricing', ['locale' => $locale], absolute: true).'): Plans, trial and limits.';
            $lines[] = '- [Contact ('.$label.')]('.route('contact.index', ['locale' => $locale], absolute: true).'): Contact form.';
        }

        $lines[] = '';
        $lines[] = '## FAQ topics (English titles)';
        $lines[] = '';

        $previousLocale = app()->getLocale();
        app()->setLocale('en');
        foreach (FaqSections::orderedItems() as $item) {
            $title = (string) ($item['title'] ?? '');
            $summary = (string) ($item['summary'] ?? $item['intro'] ?? '');
            if ($title === '') {
                continue;
            }
            $slug = (string) ($item['slug'] ?? '');
            $anchor = $slug !== '' ? '#faq-'.$slug : '#faq';
            $url = route('welcome', ['locale' => 'en'], absolute: true).$anchor;
            $note = $summary !== '' ? ': '.$this->oneLine($summary) : '';
            $lines[] = "- [{$title}]({$url}){$note}";
        }
        app()->setLocale($previousLocale);

        $lines[] = '';
        $lines[] = '## Optional';

        foreach (config('legal.documents', []) as $meta) {
            if (! isset($meta['route'], $meta['label_key']) || ! is_string($meta['route'])) {
                continue;
            }
            $url = route($meta['route'], ['locale' => 'en'], absolute: true);
            $label = __($meta['label_key'], [], 'en');
            $lines[] = "- [{$label}]({$url}): Legal document (English).";
        }

        $lines[] = '- [Sitemap]('.url('/sitemap.xml').'): XML sitemap of marketing pages.';
        $lines[] = '- [Register]('.route('register', absolute: true).'): Start a free trial account.';
        $lines[] = '- [Log in]('.route('login', absolute: true).'): Workspace login for staff.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
