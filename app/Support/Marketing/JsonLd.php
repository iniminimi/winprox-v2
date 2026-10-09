<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Support\Faq\FaqSections;

/**
 * Schema.org JSON-LD voor marketingpagina's (Organization, SoftwareApplication, FAQPage, VideoObject).
 */
final class JsonLd
{
    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return self::withMarketLanguage([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'WinProx',
            'url' => url('/'),
            'logo' => asset('images/Winprox_logo_100.png'),
            'description' => __('welcome.social.og_description', [], 'en'),
            'sameAs' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function softwareApplication(): array
    {
        // Locale-aware: de pagina-taal bepaalt URL en beschrijving, zodat de
        // markup op /nl/ ook Nederlands spreekt.
        $offers = [];
        if (! MarketingDomain::routeHidden('checkmate')) {
            $offers[] = [
                '@type' => 'Offer',
                'name' => 'Checkmate',
                'price' => '5',
                'priceCurrency' => 'EUR',
                'description' => 'Per active worker per month',
                'url' => route('pricing', absolute: true),
            ];
        }
        $offers[] = [
            '@type' => 'Offer',
            'name' => 'Clock screen',
            'price' => (string) config('marketing.clock_rent_monthly_eur'),
            'priceCurrency' => 'EUR',
            'description' => 'Optional clock screen, per month',
            'url' => route('prikklok', absolute: true),
        ];

        return self::withMarketLanguage([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'WinProx',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => route('welcome', absolute: true),
            'description' => __('welcome.social.og_description'),
            'offers' => $offers,
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'WinProx',
                'url' => url('/'),
            ],
        ]);
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

        return self::withMarketLanguage([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ]);
    }

    /**
     * VideoObject voor pagina's met een <video>-speler — zelfde brondata als
     * de sitemap-video-entries (SitemapVideo), zodat beide kanalen matchen.
     *
     * @param  array{content_loc: string, thumbnail_loc: string, title: string, description: string, upload_date: string}  $video
     * @return array<string, mixed>
     */
    public static function videoObject(array $video): array
    {
        return self::withMarketLanguage([
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $video['title'],
            'description' => $video['description'],
            'thumbnailUrl' => $video['thumbnail_loc'],
            'contentUrl' => $video['content_loc'],
            'uploadDate' => $video['upload_date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    private static function withMarketLanguage(array $graph): array
    {
        $language = MarketingDomain::inLanguage();
        if ($language !== null) {
            $graph['inLanguage'] = $language;
        }

        return $graph;
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
