<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Marketing\MarketingDomain;
use App\Support\Marketing\MarketingSeo;
use App\Support\Marketing\SitemapVideo;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $supported = MarketingDomain::localesFor(request());
        // lastmod = curated content-datum (rules §10a verplicht deze bij te houden
        // bij content-wijzigingen); now() zou Google leren lastmod te negeren.
        $lastmod = (string) config('product_docs.documents_last_updated', '2026-01-01');

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .' xmlns:xhtml="http://www.w3.org/1999/xhtml"'
            .' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">'."\n";

        $host = MarketingDomain::normalizeHost(request()->getHost());

        foreach (MarketingSeo::indexedRouteNames() as $routeName) {
            if (MarketingDomain::routeHiddenOnHost($routeName, $host)) {
                continue;
            }
            $alternates = MarketingSeo::alternateLinks($routeName, []);

            foreach ($supported as $locale) {
                $loc = route($routeName, ['locale' => $locale], absolute: true);

                $body .= "  <url>\n";
                $body .= '    <loc>'.e($loc)."</loc>\n";
                $body .= '    <lastmod>'.$lastmod."</lastmod>\n";

                foreach ($alternates as $alt) {
                    $body .= '    <xhtml:link rel="alternate" hreflang="'
                        .e($alt['hreflang']).'" href="'.e($alt['href']).'"/>'."\n";
                }

                foreach (SitemapVideo::forRoute($routeName, $locale) as $video) {
                    $body .= "    <video:video>\n";
                    $body .= '      <video:thumbnail_loc>'.e($video['thumbnail_loc'])."</video:thumbnail_loc>\n";
                    $body .= '      <video:title>'.e($video['title'])."</video:title>\n";
                    $body .= '      <video:description>'.e($video['description'])."</video:description>\n";
                    $body .= '      <video:content_loc>'.e($video['content_loc'])."</video:content_loc>\n";
                    $body .= '      <video:family_friendly>yes</video:family_friendly>'."\n";
                    $body .= "    </video:video>\n";
                }

                $body .= "  </url>\n";
            }
        }

        $body .= '</urlset>';

        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
