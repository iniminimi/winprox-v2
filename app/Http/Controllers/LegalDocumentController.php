<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Marketing\BuildLegalDocumentMarkdownAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function show(Request $request, string $doc): View|Response
    {
        if ($this->wantsMarkdown($request)) {
            return $this->markdown($request, $doc);
        }

        $documents = config('legal.documents', []);

        abort_unless(array_key_exists($doc, $documents), 404);

        $meta = $documents[$doc];
        $locale = app()->getLocale();
        $updatedRaw = config('legal.documents_last_updated', '2026-05-10');
        $updatedAt = \Illuminate\Support\Carbon::parse($updatedRaw)->format('d/m/Y');

        return view('layouts.components.legal', [
            'doc' => $doc,
            'meta' => $meta,
            'locale' => $locale,
            'title' => __($meta['label_key']),
            'updatedAt' => $updatedAt,
        ]);
    }

    public function markdown(Request $request, string $doc): Response
    {
        $markdown = app(BuildLegalDocumentMarkdownAction::class)->handle($doc, app()->getLocale());

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
