<?php

declare(strict_types=1);

namespace App\Support\Manual;

use App\Support\PageHelp;

final class ManualChapters
{
    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, title: string, actions: list<array{label: string, text: string, nested: bool}>, statuses: list<array{key: string, label: string, text: string, pill: string}>, status_note: string|null}>
     */
    public static function fromPageHelp(array $keys): array
    {
        $chapters = [];

        foreach ($keys as $key) {
            $data = PageHelp::for($key);

            if ($data === null) {
                continue;
            }

            $data['title'] = preg_replace('/^[^\x{2014}]+\x{2014} /u', '', $data['title']);
            $chapters[] = array_merge(['key' => $key], $data);
        }

        return $chapters;
    }
}
