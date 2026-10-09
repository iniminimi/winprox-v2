<?php

namespace App\Support;

use App\Support\Marketing\MarketingDomain;
use Illuminate\Translation\FileLoader;

/**
 * Laat per-page JSON-vertaalbestanden toe: lang/[locale]/[page].json
 * met dot-notatie, bv. __('common.status.new') -> lang/nl/common.json.
 *
 * PHP-groepbestanden blijven werken; JSON wordt er overheen gemerged.
 *
 * Op een live marktdomein (winprox.nl, winprox.be) wordt daarna een
 * markt-overlay gemerged: lang/markets/[market]/[locale]/[page].json.
 * Alleen verschillende keys staan in de overlay; de rest valt terug
 * op de gemeenschappelijke basis. Console/queue heeft geen markt.
 */
class JsonTranslationLoader extends FileLoader
{
    protected function loadPaths(array $paths, $locale, $group)
    {
        $output = parent::loadPaths($paths, $locale, $group);

        $market = MarketingDomain::marketOverlay();
        $overlays = $market !== null ? [$market] : [];

        foreach ($paths as $path) {
            $full = "{$path}/{$locale}/{$group}.json";

            if ($this->files->exists($full)) {
                $decoded = json_decode($this->files->get($full), true);

                if (is_array($decoded)) {
                    $output = array_replace_recursive($output, $decoded);
                }
            }

            foreach ($overlays as $market) {
                $full = "{$path}/markets/{$market}/{$locale}/{$group}.json";

                if ($this->files->exists($full)) {
                    $decoded = json_decode($this->files->get($full), true);

                    if (is_array($decoded)) {
                        $output = array_replace_recursive($output, $decoded);
                    }
                }
            }
        }

        return $output;
    }
}
