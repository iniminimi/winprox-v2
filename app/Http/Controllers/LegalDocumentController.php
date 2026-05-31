<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function show(Request $request, string $doc): View
    {
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
}
