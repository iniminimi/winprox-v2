<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Constrained HTML → Markdown for public legal pages (headings, lists, tables, links).
 */
final class HtmlToMarkdown
{
    public static function convert(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="wp-md-root">'
            .$html
            .'</div></body></html>';

        libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('wp-md-root');
        if (! $root instanceof DOMElement) {
            return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $markdown = self::childrenToBlocks($root);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return trim($markdown);
    }

    private static function childrenToBlocks(DOMNode $parent): string
    {
        $parts = [];

        foreach ($parent->childNodes as $child) {
            $chunk = self::nodeToMarkdown($child);
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return implode("\n\n", $parts);
    }

    private static function nodeToMarkdown(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = self::collapseInline((string) $node->nodeValue);

            return $text === '' ? '' : $text;
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        return match ($tag) {
            'h1' => '# '.self::inlineChildren($node),
            'h2' => '## '.self::inlineChildren($node),
            'h3' => '### '.self::inlineChildren($node),
            'h4' => '#### '.self::inlineChildren($node),
            'p' => self::inlineChildren($node),
            'br' => '',
            'ul' => self::listToMarkdown($node, ordered: false),
            'ol' => self::listToMarkdown($node, ordered: true),
            'table' => self::tableToMarkdown($node),
            'strong', 'b' => '**'.self::inlineChildren($node).'**',
            'em', 'i' => '*'.self::inlineChildren($node).'*',
            'a' => self::linkToMarkdown($node),
            'div', 'span', 'section', 'article', 'header', 'footer' => self::childrenToBlocks($node),
            'thead', 'tbody', 'tr', 'th', 'td', 'li' => self::inlineChildren($node),
            default => self::childrenToBlocks($node),
        };
    }

    private static function listToMarkdown(DOMElement $list, bool $ordered): string
    {
        $lines = [];
        $index = 1;

        foreach ($list->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $item = trim(self::inlineChildren($child));
            if ($item === '') {
                continue;
            }

            $prefix = $ordered ? $index.'. ' : '- ';
            $lines[] = $prefix.$item;
            $index++;
        }

        return implode("\n", $lines);
    }

    private static function tableToMarkdown(DOMElement $table): string
    {
        $rows = [];

        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }
                $cellTag = strtolower($cell->tagName);
                if ($cellTag !== 'th' && $cellTag !== 'td') {
                    continue;
                }
                $cells[] = str_replace('|', '\\|', trim(self::inlineChildren($cell)));
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === []) {
            return '';
        }

        $width = max(array_map('count', $rows));
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = array_pad($row, $width, '');
        }

        $header = $normalized[0];
        $lines = [
            '| '.implode(' | ', $header).' |',
            '| '.implode(' | ', array_fill(0, $width, '---')).' |',
        ];

        foreach (array_slice($normalized, 1) as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        return implode("\n", $lines);
    }

    private static function linkToMarkdown(DOMElement $anchor): string
    {
        $label = self::inlineChildren($anchor);
        $href = trim($anchor->getAttribute('href'));
        if ($href === '') {
            return $label;
        }

        return '['.$label.']('.$href.')';
    }

    private static function inlineChildren(DOMNode $parent): string
    {
        $text = '';

        foreach ($parent->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= (string) $child->nodeValue;

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            $text .= match ($tag) {
                'br' => "\n",
                'strong', 'b' => '**'.self::inlineChildren($child).'**',
                'em', 'i' => '*'.self::inlineChildren($child).'*',
                'a' => self::linkToMarkdown($child),
                default => self::inlineChildren($child),
            };
        }

        return self::collapseInline($text);
    }

    private static function collapseInline(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;

        return trim($text);
    }
}
