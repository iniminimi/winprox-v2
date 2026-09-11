<?php

declare(strict_types=1);

it('serveert DPA en subverwerkers als Markdown', function () {
    $this->get(route('legal.dpa.md', ['locale' => 'en']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee('# Data processing agreement (DPA)', false)
        ->assertSee('Article 28 GDPR', false)
        ->assertSee('Cloud86', false);

    $this->get(route('legal.subprocessors.md', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('# Subverwerkers', false)
        ->assertSee('Cloud86', false)
        ->assertSee('|', false);

    $this->withHeader('Accept', 'text/markdown')
        ->get(route('legal.dpa', ['locale' => 'en']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee('# Data processing agreement (DPA)', false);
});

it('redirect /legal/dpa.md naar gelokaliseerde Markdown-URL', function () {
    $this->get('/legal/dpa.md')
        ->assertRedirect();
});

it('serveert llms-full.txt met fiches en juridische pagina\'s', function () {
    $this->get(route('llms.full'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee('# WinProx — full documentation (English)', false)
        ->assertSee('# Technical fact sheet', false)
        ->assertSee('# Features overview', false)
        ->assertSee('# API & Webhooks', false)
        ->assertSee('# Data processing agreement (DPA)', false)
        ->assertSee('# Subprocessors', false);
});

it('zet markdown-alternate op de HTML DPA', function () {
    $this->get(route('legal.dpa', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('type="text/markdown"', false)
        ->assertSee(route('legal.dpa.md', ['locale' => 'en']), false);
});

it('zet geen sessiecookies op crawler-documenten', function (string $path) {
    $response = $this->get($path);

    $response->assertOk();
    expect($response->headers->getCookies())->toBeEmpty()
        ->and($response->headers->get('Cache-Control'))->toContain('public');
})->with([
    'llms' => '/llms.txt',
    'llms-full' => '/llms-full.txt',
    'technical-md' => '/en/docs/technical.md',
    'dpa-md' => '/en/legal/dpa.md',
    'technical-html' => '/en/docs/technical',
    'dpa-html' => '/en/legal/dpa',
]);

it('laat Perplexity-crawlers toe in robots.txt', function () {
    $robots = (string) file_get_contents(public_path('robots.txt'));

    expect($robots)
        ->toContain('User-agent: PerplexityBot')
        ->toContain('User-agent: Perplexity-User')
        ->toContain('Allow: /');
});
