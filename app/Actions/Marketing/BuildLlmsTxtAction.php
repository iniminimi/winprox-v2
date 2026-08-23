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
            $about = route('about', ['locale' => $locale], absolute: true);
            $faq = route('faq.public', ['locale' => $locale], absolute: true);
            $hospitality = route('hospitality', ['locale' => $locale], absolute: true);
            $industry = route('industry', ['locale' => $locale], absolute: true);
            $healthcare = route('healthcare', ['locale' => $locale], absolute: true);
            $government = route('government', ['locale' => $locale], absolute: true);
            $realestate = route('realestate', ['locale' => $locale], absolute: true);
            $lines[] = "- [Homepage ({$label})]({$home}): Marketing overview of Facility, Time and ESG.";
            $lines[] = "- [Hospitality ({$label})]({$hospitality}): Facility landing for hotels and restaurants.";
            $lines[] = "- [Industry ({$label})]({$industry}): Facility landing for plants and production sites.";
            $lines[] = "- [Healthcare ({$label})]({$healthcare}): Facility landing for hospitals and care sites.";
            $lines[] = "- [Government ({$label})]({$government}): Facility landing for municipalities and public services.";
            $lines[] = "- [Real estate ({$label})]({$realestate}): Facility landing for buildings, tenants and property teams.";
            $lines[] = "- [About ({$label})]({$about}): What WinProx is, who it is for, modules and compliance.";
            $lines[] = "- [FAQ ({$label})]({$faq}): Full frequently asked questions, always visible in HTML for readers and crawlers.";
        }

        $lines[] = '';
        $lines[] = '## Features';

        foreach (config('locales.supported', []) as $locale) {
            $label = strtoupper((string) $locale);
            $lines[] = '- [Facility ('.$label.')]('.route('features.facility', ['locale' => $locale], absolute: true).'): Issues, tasks, moderation and facility workflows via QR.';
            $lines[] = '- [Time ('.$label.')]('.route('features.time', ['locale' => $locale], absolute: true).'): Clock Point presence, breaks and shifts.';
            $lines[] = '- [ESG ('.$label.')]('.route('features.esg', ['locale' => $locale], absolute: true).'): Optional ESG measurements on the same portals.';
            $lines[] = '- [IoT Connect ('.$label.')]('.route('features.iot', ['locale' => $locale], absolute: true).'): Sensor gateway ingest: alarms become issues; Corporate also records ESG measurements.';
            $lines[] = '- [QR portals ('.$label.')]('.route('features.qr', ['locale' => $locale], absolute: true).'): Unit QR and Clock Point QR without a native app.';
            $lines[] = '- [API & Webhooks ('.$label.')]('.route('product.api_webhooks', ['locale' => $locale], absolute: true).'): REST API and webhooks fact sheet for integrators.';
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
        $faqBase = route('faq.public', ['locale' => 'en'], absolute: true);
        foreach (FaqSections::orderedItems() as $item) {
            $title = (string) ($item['title'] ?? '');
            $summary = (string) ($item['summary'] ?? $item['intro'] ?? '');
            if ($title === '') {
                continue;
            }
            $slug = (string) ($item['slug'] ?? '');
            $url = $slug !== '' ? $faqBase.'#faq-'.$slug : $faqBase;
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
