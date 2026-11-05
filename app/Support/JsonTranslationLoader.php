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
                    $output = $this->mergeTranslations($output, $decoded);
                }
            }

            foreach ($overlays as $market) {
                $full = "{$path}/markets/{$market}/{$locale}/{$group}.json";

                if ($this->files->exists($full)) {
                    $decoded = json_decode($this->files->get($full), true);

                    if (is_array($decoded)) {
                        $output = $this->mergeTranslations($output, $decoded);
                    }
                }
            }
        }

        return $output;
    }

    /**
     * Zelfde merge als array_replace_recursive, behalve een lijst van kaarten
     * met `key`: een overlay-sleutel die zo'n key is, landt op die kaart.
     * Een numerieke sleutel blijft de plaats in de lijst. Een onbekende
     * kaartnaam wordt niet als extra kaart toegevoegd.
     *
     * @param  array<mixed>  $base
     * @param  array<mixed>  $overlay
     * @return array<mixed>
     */
    public function mergeTranslations(array $base, array $overlay): array
    {
        if ($this->isCardList($base)) {
            return $this->mergeCardList($base, $overlay);
        }

        $merged = $base;
        foreach ($overlay as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->mergeTranslations($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isCardList(array $value): bool
    {
        if ($value === [] || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (is_array($item) && isset($item['key']) && is_string($item['key']) && $item['key'] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $base
     * @param  array<mixed>  $overlay
     * @return array<mixed>
     */
    private function mergeCardList(array $base, array $overlay): array
    {
        $indexByKey = [];
        foreach ($base as $index => $item) {
            if (is_array($item) && isset($item['key']) && is_string($item['key']) && $item['key'] !== '') {
                $indexByKey[$item['key']] = $index;
            }
        }

        foreach ($overlay as $key => $value) {
            if (is_string($key) && isset($indexByKey[$key]) && is_array($value)) {
                $index = $indexByKey[$key];
                $base[$index] = $this->mergeTranslations($base[$index], $value);

                continue;
            }

            if (! is_int($key) && ! (is_string($key) && ctype_digit($key))) {
                continue;
            }

            $index = (int) $key;
            if (! array_key_exists($index, $base)) {
                continue;
            }

            if (is_array($value) && is_array($base[$index])) {
                $base[$index] = $this->mergeTranslations($base[$index], $value);
            } else {
                $base[$index] = $value;
            }
        }

        return $base;
    }
}
