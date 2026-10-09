<?php

namespace App\Actions\Marketing;

use App\Support\Marketing\MarketingDomain;

/**
 * Bouwt /llms.txt (Markdown) zodat AI-bots WinProx gericht kunnen indexeren.
 */
class BuildLlmsTxtAction
{
    public function handle(): string
    {
        $checkmateHidden = MarketingDomain::routeHidden('checkmate');

        $lines = [
            '# WinProx',
            '',
            $checkmateHidden
                ? '> Digital time clock for time tracking. Staff clock in with their phone, without an app. Hours sit next to the roster and are passed to payroll administration.'
                : '> Digital time clock for time tracking. Staff clock in with their phone, without an app. Hours sit next to the roster and are passed to the social secretariat. Presence reporting to the social security office (CIAO) is optional, for crews that work at customer sites.',
            '',
            'Languages: Dutch, English, French, German, Spanish, Italian.',
            '',
            'Public product fact sheets and legal pages are also available as Markdown (`…/docs/{name}.md`, `…/legal/{name}.md`) and as one English dump: '.url('/llms-full.txt').'.',
            '',
            '## Product pages',
        ];

        foreach ($this->locales() as $locale) {
            $label = strtoupper((string) $locale);
            $home = $this->pageUrl('welcome', $locale);
            $about = $this->pageUrl('about', $locale);
            $prikklok = $this->pageUrl('prikklok', $locale);
            $lines[] = '- [Homepage ('.$label.')]('.$home.'): Digital time clock for time tracking. Staff clock in with their phone, without an app. Hours are compared with the roster and passed to the social secretariat.';
            $lines[] = '- [Time clock ('.$label.')]('.$prikklok.'): Digital punch clock — workers clock in with their own phone via a QR code; optional clock screen shows a rotating QR.';
            if (! $checkmateHidden) {
                $checkmate = $this->pageUrl('checkmate', $locale);
                $lines[] = '- [Checkmate ('.$label.')]('.$checkmate.'): Optional presence at the customer for the social security office (CIAO). Cleaning for third parties now. Construction on site from 1 April 2027.';
            }
            $lines[] = '- [Time ('.$label.')]('.$this->pageUrl('features.time', $locale).'): Clock Point presence, breaks and shifts.';
            $lines[] = '- [About ('.$label.')]('.$about.'): What WinProx is: a digital time clock, hours next to the roster'
                .($checkmateHidden ? '.' : ', and optional CIAO.');
        }

        $lines[] = '';
        $lines[] = '## Product fact sheets';

        foreach ($this->locales() as $locale) {
            $label = strtoupper((string) $locale);
            $lines[] = '- [API & Webhooks ('.$label.')]('.$this->pageUrl('product.api_webhooks.md', $locale).'): REST API and webhooks fact sheet for integrators (Markdown).';
            $lines[] = '- [Features overview ('.$label.')]('.$this->pageUrl('product.features.md', $locale).'): Product fact sheet aligned with the in-app manual (Markdown).';
            $lines[] = '- [Technical fact sheet ('.$label.')]('.$this->pageUrl('product.technical.md', $locale).'): Hosting, GDPR, backups, auth and security for IT (Markdown).';
        }

        $lines[] = '';
        $lines[] = '## Pricing & contact';

        foreach ($this->locales() as $locale) {
            $label = strtoupper((string) $locale);
            $lines[] = '- [Pricing ('.$label.')]('.$this->pageUrl('pricing', $locale).'): Plans, trial and limits.';
            $lines[] = '- [Contact ('.$label.')]('.$this->pageUrl('contact.index', $locale).'): Contact form.';
        }

        $lines[] = '';
        $lines[] = '## Optional';

        foreach (config('legal.documents', []) as $meta) {
            if (! isset($meta['route'], $meta['label_key']) || ! is_string($meta['route'])) {
                continue;
            }
            $url = $this->pageUrl($meta['route'].'.md', 'en');
            $label = __($meta['label_key'], [], 'en');
            $lines[] = "- [{$label}]({$url}): Legal document in Markdown (English).";
        }

        $lines[] = '- [Full documentation dump]('.url('/llms-full.txt').'): English Markdown of features, technical, API & Webhooks, DPA, subprocessors and other legal pages.';
        $lines[] = '- [Sitemap]('.url('/sitemap.xml').'): XML sitemap of marketing pages.';
        $lines[] = '- [Register]('.MarketingDomain::authUrl('/register').'): Start a free trial account.';
        $lines[] = '- [Log in]('.MarketingDomain::authUrl('/login').'): Workspace login for staff.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return MarketingDomain::localesFor(request());
    }

    private function pageUrl(string $name, string $locale): string
    {
        if (! MarketingDomain::isLiveMarketRequest()) {
            return route($name, ['locale' => $locale], absolute: true);
        }

        $origin = in_array($locale, $this->locales(), true)
            ? 'https://'.MarketingDomain::normalizeHost(request()->getHost())
            : MarketingDomain::hreflangAppOrigin();

        return MarketingDomain::absoluteRoute($origin, $name, ['locale' => $locale]);
    }
}
