<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RobotsTxtController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $origin = $request->getSchemeAndHttpHost();

        $body = <<<TXT
User-agent: PerplexityBot
Allow: /

User-agent: Perplexity-User
Allow: /

User-agent: GPTBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: OAI-SearchBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: *
Allow: /
Disallow: /login
Disallow: /register
Disallow: /forgot-password
Disallow: /reset-password
Disallow: /email
Disallow: /melden
Disallow: /time
Disallow: /cp
Disallow: /q
Disallow: /u
Disallow: /api
Disallow: /platform
Disallow: /dashboard

Sitemap: {$origin}/sitemap.xml

# Curated index for AI / LLM crawlers (https://llmstxt.org/)
# {$origin}/llms.txt
# {$origin}/llms-full.txt
TXT;

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
