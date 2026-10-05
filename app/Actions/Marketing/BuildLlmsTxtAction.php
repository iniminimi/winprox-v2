<?php

namespace App\Actions\Marketing;

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
            '> Digital time clock for time tracking. Staff clock in with their phone, without an app. Hours sit next to the roster and are passed to the social secretariat. Presence reporting to the social security office (CIAO) is optional, for crews that work at customer sites.',
            '',
            'Languages: Dutch, English, French, German, Spanish, Italian.',
            '',
            'Public product fact sheets and legal pages are also available as Markdown (`…/docs/{name}.md`, `…/legal/{name}.md`) and as one English dump: '.url('/llms-full.txt').'.',
            '',
            '## Product pages',
        ];

        foreach (config('locales.supported', []) as $locale) {
            $label = strtoupper((string) $locale);
            $home = route('welcome', ['locale' => $locale], absolute: true);
            $about = route('about', ['locale' => $locale], absolute: true);
            $checkmate = route('checkmate', ['locale' => $locale], absolute: true);
            $prikklok = route('prikklok', ['locale' => $locale], absolute: true);
            $lines[] = '- [Homepage ('.$label.')]('.$home.'): Digital time clock for time tracking. Staff clock in with their phone, without an app. Hours are compared with the roster and passed to the social secretariat.';
            $lines[] = '- [Time clock ('.$label.')]('.$prikklok.'): Digital punch clock — workers clock in with their own phone via a QR code; optional clock screen shows a rotating QR.';
            $lines[] = '- [Checkmate ('.$label.')]('.$checkmate.'): Optional presence at the customer for the social security office (CIAO). Cleaning for third parties now. Construction on site from 1 April 2027.';
            $lines[] = '- [Time ('.$label.')]('.route('features.time', ['locale' => $locale], absolute: true).'): Clock Point presence, breaks and shifts.';
            $lines[] = '- [About ('.$label.')]('.$about.'): What WinProx is: a digital time clock, hours next to the roster, and optional CIAO.';
        }

        $lines[] = '';
        $lines[] = '## Product fact sheets';

        foreach (config('locales.supported', []) as $locale) {
            $label = strtoupper((string) $locale);
            $lines[] = '- [API & Webhooks ('.$label.')]('.route('product.api_webhooks.md', ['locale' => $locale], absolute: true).'): REST API and webhooks fact sheet for integrators (Markdown).';
            $lines[] = '- [Features overview ('.$label.')]('.route('product.features.md', ['locale' => $locale], absolute: true).'): Product fact sheet aligned with the in-app manual (Markdown).';
            $lines[] = '- [Technical fact sheet ('.$label.')]('.route('product.technical.md', ['locale' => $locale], absolute: true).'): Hosting, GDPR, backups, auth and security for IT (Markdown).';
        }

        $lines[] = '';
        $lines[] = '## Pricing & contact';

        foreach (config('locales.supported', []) as $locale) {
            $label = strtoupper((string) $locale);
            $lines[] = '- [Pricing ('.$label.')]('.route('pricing', ['locale' => $locale], absolute: true).'): Plans, trial and limits.';
            $lines[] = '- [Contact ('.$label.')]('.route('contact.index', ['locale' => $locale], absolute: true).'): Contact form.';
        }

        $lines[] = '';
        $lines[] = '## Optional';

        foreach (config('legal.documents', []) as $meta) {
            if (! isset($meta['route'], $meta['label_key']) || ! is_string($meta['route'])) {
                continue;
            }
            $url = route($meta['route'].'.md', ['locale' => 'en'], absolute: true);
            $label = __($meta['label_key'], [], 'en');
            $lines[] = "- [{$label}]({$url}): Legal document in Markdown (English).";
        }

        $lines[] = '- [Full documentation dump]('.url('/llms-full.txt').'): English Markdown of features, technical, API & Webhooks, DPA, subprocessors and other legal pages.';
        $lines[] = '- [Sitemap]('.url('/sitemap.xml').'): XML sitemap of marketing pages.';
        $lines[] = '- [Register]('.route('register', absolute: true).'): Start a free trial account.';
        $lines[] = '- [Log in]('.route('login', absolute: true).'): Workspace login for staff.';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
