<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

/**
 * Eén Markdown-dump (Engels) van productfiches + juridische pagina's voor AI-crawlers.
 */
class BuildLlmsFullTxtAction
{
    public function __construct(
        private BuildProductDocumentMarkdownAction $productMarkdown,
        private BuildLegalDocumentMarkdownAction $legalMarkdown,
    ) {}

    public function handle(): string
    {
        $sections = [
            '# WinProx — full documentation (English)',
            '',
            '> Machine-readable dump of public product fact sheets and legal pages. HTML versions stay canonical for humans.',
            '',
            'Source index: '.url('/llms.txt'),
            '',
        ];

        foreach (array_keys(config('product_docs.documents', [])) as $doc) {
            $markdown = $this->productMarkdown->handle((string) $doc, 'en');
            if (! is_string($markdown) || trim($markdown) === '') {
                continue;
            }
            $sections[] = $markdown;
            $sections[] = '';
            $sections[] = '---';
            $sections[] = '';
        }

        foreach (array_keys(config('legal.documents', [])) as $doc) {
            $markdown = $this->legalMarkdown->handle((string) $doc, 'en');
            if (! is_string($markdown) || trim($markdown) === '') {
                continue;
            }
            $sections[] = $markdown;
            $sections[] = '';
            $sections[] = '---';
            $sections[] = '';
        }

        $body = implode("\n", $sections);
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;
        $body = preg_replace("/\n---\n*$/", "\n", $body) ?? $body;

        return trim($body)."\n";
    }
}
