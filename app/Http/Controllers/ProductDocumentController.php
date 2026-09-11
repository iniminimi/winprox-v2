<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Marketing\BuildProductDocumentMarkdownAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductDocumentController extends Controller
{
    public function show(Request $request, string $doc): View|Response
    {
        if ($this->wantsMarkdown($request)) {
            return $this->markdown($request, $doc);
        }

        $documents = config('product_docs.documents', []);

        abort_unless(array_key_exists($doc, $documents), 404);

        $meta = $documents[$doc];
        $locale = app()->getLocale();
        $updatedRaw = config('product_docs.documents_last_updated', '2026-07-30');
        $updatedAt = \Illuminate\Support\Carbon::parse($updatedRaw)->format('d/m/Y');
        $contentKey = (string) ($meta['content_key'] ?? $doc);
        /** @var array<string, mixed> $content */
        $content = __('product_docs.'.$contentKey);

        abort_unless(is_array($content) && isset($content['label']), 404);

        return view('layouts.components.product-doc', [
            'doc' => $doc,
            'meta' => $meta,
            'locale' => $locale,
            'title' => (string) $content['label'],
            'updatedAt' => $updatedAt,
            'content' => $content,
        ]);
    }

    public function markdown(Request $request, string $doc): Response
    {
        $markdown = app(BuildProductDocumentMarkdownAction::class)->handle($doc, app()->getLocale());

        abort_unless(is_string($markdown) && $markdown !== '', 404);

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function wantsMarkdown(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept', '')), 'text/markdown');
    }
}
