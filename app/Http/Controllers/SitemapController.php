<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Marketing\MarketingSeo;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $supported = config('locales.supported', []);
        $lastmod = now()->toDateString();

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .' xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach (MarketingSeo::routeNames() as $routeName) {
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

                $body .= "  </url>\n";
            }
        }

        $body .= '</urlset>';

        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
