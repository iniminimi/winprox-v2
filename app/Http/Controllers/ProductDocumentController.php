<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductDocumentController extends Controller
{
    public function show(Request $request, string $doc): View
    {
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
}
