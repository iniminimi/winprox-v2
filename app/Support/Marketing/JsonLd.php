<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Support\Faq\FaqSections;

/**
 * Schema.org JSON-LD voor marketingpagina's (Organization, SoftwareApplication, FAQPage).
 */
final class JsonLd
{
    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'WinProx',
            'url' => url('/'),
            'logo' => asset('images/Winprox_logo_100.png'),
            'description' => __('welcome.social.og_description', [], 'en'),
            'sameAs' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function softwareApplication(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'WinProx',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => route('welcome', ['locale' => 'en'], absolute: true),
            'description' => 'QR-code based facility management platform: report issues, assign tasks to teams, clock presence (Time), and optionally track ESG — in the browser without a native app.',
            'offers' => [
                '@type' => 'AggregateOffer',
                'url' => route('pricing', ['locale' => 'en'], absolute: true),
                'priceCurrency' => 'EUR',
                'lowPrice' => '149',
                'highPrice' => '999',
                'offerCount' => 3,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'WinProx',
                'url' => url('/'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function faqPage(): array
    {
        $entities = [];

        foreach (FaqSections::orderedItems() as $item) {
            $question = (string) ($item['title'] ?? '');
            $answer = self::faqAnswerText($item);
            if ($question === '' || $answer === '') {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function faqAnswerText(array $item): string
    {
        foreach (['summary', 'intro', 'body'] as $key) {
            $value = $item[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
