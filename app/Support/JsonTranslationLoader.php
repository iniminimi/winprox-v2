<?php

namespace App\Support;

use Illuminate\Translation\FileLoader;

/**
 * Laat per-page JSON-vertaalbestanden toe: lang/[locale]/[page].json
 * met dot-notatie, bv. __('common.status.new') -> lang/nl/common.json.
 *
 * PHP-groepbestanden blijven werken; JSON wordt er overheen gemerged.
 */
class JsonTranslationLoader extends FileLoader
{
    protected function loadPaths(array $paths, $locale, $group)
    {
        $output = parent::loadPaths($paths, $locale, $group);

        foreach ($paths as $path) {
            $full = "{$path}/{$locale}/{$group}.json";

            if ($this->files->exists($full)) {
                $decoded = json_decode($this->files->get($full), true);

                if (is_array($decoded)) {
                    $output = array_replace_recursive($output, $decoded);
                }
            }
        }

        return $output;
    }
}
