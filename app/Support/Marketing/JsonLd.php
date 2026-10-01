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
            'description' => 'Work on your site, simply well organised: from reporting and planning to execution and completion — via QR in the browser, no app.',
            'offers' => [
                '@type' => 'AggregateOffer',
                'url' => route('pricing', ['locale' => 'en'], absolute: true),
                'priceCurrency' => 'EUR',
                'lowPrice' => '840',
                'highPrice' => '3000',
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
     * Zelfde bron als de zichtbare FAQ: render de body-partial en strip de
     * markup, zodat acceptedAnswer.text 1-op-1 overeenkomt met wat de bezoeker
     * leest — ook voor rijke types (steps, pricing, portal, roles).
     *
     * @param  array<string, mixed>  $item
     */
    private static function faqAnswerText(array $item): string
    {
        $html = view('partials.wp-faq-item-body', ['item' => $item])->render();
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
