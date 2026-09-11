<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

/**
 * Zet een publieke productfiche (JSON) om naar Markdown voor AI-crawlers.
 */
class BuildProductDocumentMarkdownAction
{
    public function handle(string $doc, string $locale): ?string
    {
        $documents = config('product_docs.documents', []);
        if (! array_key_exists($doc, $documents) || ! is_array($documents[$doc])) {
            return null;
        }

        $meta = $documents[$doc];
        $contentKey = (string) ($meta['content_key'] ?? $doc);
        $content = __('product_docs.'.$contentKey, [], $locale);
        if (! is_array($content) || ! isset($content['label'])) {
            return null;
        }

        $updatedRaw = config('product_docs.documents_last_updated', '2026-07-30');
        $updatedAt = \Illuminate\Support\Carbon::parse($updatedRaw)->format('d/m/Y');

        $lines = [
            '# '.(string) $content['label'],
            '',
        ];

        if (! empty($content['tagline']) && is_string($content['tagline'])) {
            $lines[] = $content['tagline'];
            $lines[] = '';
        }

        $lines[] = __('product_docs.chrome.last_updated', ['date' => $updatedAt], $locale);
        $lines[] = '';

        if (! empty($content['highlight']) && is_string($content['highlight'])) {
            $lines[] = '**'.$content['highlight'].'**';
            $lines[] = '';
        }

        if (! empty($content['intro']) && is_string($content['intro'])) {
            $lines[] = $content['intro'];
            $lines[] = '';
        }

        if (! empty($content['flow']) && is_array($content['flow'])) {
            $steps = array_values(array_filter($content['flow'], 'is_string'));
            if ($steps !== []) {
                $lines[] = implode(' → ', $steps);
                $lines[] = '';
            }
        }

        if (! empty($content['source']) && is_string($content['source'])) {
            $lines[] = '_'.$content['source'].'_';
            $lines[] = '';
        }

        foreach (['left', 'right', 'full'] as $column) {
            if (empty($content[$column]) || ! is_array($content[$column])) {
                continue;
            }
            foreach ($content[$column] as $card) {
                if (! is_array($card)) {
                    continue;
                }
                $cardMarkdown = $this->cardToMarkdown($card);
                if ($cardMarkdown !== '') {
                    $lines[] = $cardMarkdown;
                    $lines[] = '';
                }
            }
        }

        $footer = trim(implode(' ', array_filter([
            is_string($content['footer_left'] ?? null) ? $content['footer_left'] : null,
            is_string($content['footer_right'] ?? null) ? $content['footer_right'] : null,
        ])));
        if ($footer !== '') {
            $lines[] = '_'.$footer.'_';
        }

        return trim(implode("\n", $lines))."\n";
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function cardToMarkdown(array $card): string
    {
        $title = trim((string) ($card['title'] ?? ''));
        if ($title === '') {
            return '';
        }

        $badge = trim((string) ($card['badge'] ?? ''));
        $heading = $badge !== '' ? $title.' ('.$badge.')' : $title;

        $parts = ['## '.$heading];

        if (! empty($card['body']) && is_string($card['body'])) {
            $parts[] = $card['body'];
        }

        if (! empty($card['items']) && is_array($card['items'])) {
            foreach ($card['items'] as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $parts[] = '- '.$item;
                }
            }
        }

        if (! empty($card['table']) && is_array($card['table'])) {
            $table = $this->tableToMarkdown($card['table']);
            if ($table !== '') {
                $parts[] = $table;
            }
        }

        if (! empty($card['emphasis']) && is_string($card['emphasis'])) {
            $parts[] = '_'.$card['emphasis'].'_';
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $table
     */
    private function tableToMarkdown(array $table): string
    {
        $headers = $table['headers'] ?? [];
        $rows = $table['rows'] ?? [];
        if (! is_array($headers) || $headers === [] || ! is_array($rows)) {
            return '';
        }

        $headerCells = array_map(static fn ($cell) => str_replace('|', '\\|', (string) $cell), $headers);
        $width = count($headerCells);
        $lines = [
            '| '.implode(' | ', $headerCells).' |',
            '| '.implode(' | ', array_fill(0, $width, '---')).' |',
        ];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cells = array_map(
                static fn ($cell) => str_replace('|', '\\|', (string) $cell),
                array_pad(array_values($row), $width, ''),
            );
            $lines[] = '| '.implode(' | ', array_slice($cells, 0, $width)).' |';
        }

        return implode("\n", $lines);
    }
}
