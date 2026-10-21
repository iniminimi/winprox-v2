<?php

namespace App\Livewire\Pages;

use App\Support\Marketing\JsonLd;
use App\Support\Marketing\MarketingSeo;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class ArticleTijdsregistratie2027 extends Component
{
    private const KEY = 'articles.tijdsregistratie_2027';

    public function render()
    {
        return view('livewire.pages.article-tijdsregistratie-2027', [
            'relatedLinks' => $this->relatedLinks(),
        ])->layout('components.layouts.marketing', [
            'title' => __(self::KEY.'.meta_title'),
            'socialTitle' => __(self::KEY.'.social.og_title'),
            'socialDescription' => __(self::KEY.'.social.og_description'),
            'jsonLdGraphs' => [
                JsonLd::organization(),
                $this->articleGraph(),
                $this->faqGraph(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function articleGraph(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => __(self::KEY.'.title'),
            'description' => __(self::KEY.'.social.og_description'),
            'inLanguage' => app()->getLocale(),
            'datePublished' => '2026-10-21',
            'dateModified' => '2026-10-21',
            'author' => ['@type' => 'Organization', 'name' => 'WinProx', 'url' => url('/')],
            'publisher' => ['@type' => 'Organization', 'name' => 'WinProx', 'url' => url('/')],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => MarketingSeo::canonicalUrl() ?? url()->current(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function faqGraph(): array
    {
        $entities = [];

        foreach (__(self::KEY.'.faq.items') as $item) {
            $q = (string) ($item['q'] ?? '');
            $a = (string) ($item['a'] ?? '');
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'inLanguage' => app()->getLocale(),
            'mainEntity' => $entities,
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function relatedLinks(): array
    {
        return [
            ['label' => __('landings.prikklok.nav_label'), 'url' => route('prikklok')],
            ['label' => __('features.shared.links.pricing'), 'url' => route('pricing')],
            ['label' => __('about.links.time'), 'url' => route('features.time')],
            ['label' => __('articles.tijdsregistratie_2027.winprox.cta_demo'), 'url' => route('demo')],
        ];
    }
}
