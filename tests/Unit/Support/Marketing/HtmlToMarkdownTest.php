<?php

declare(strict_types=1);

use App\Support\Marketing\HtmlToMarkdown;

it('zet juridische HTML om naar Markdown', function () {
    $html = <<<'HTML'
<h2>Overview</h2>
<p>WinProx uses <strong>Cloud86</strong> for hosting.</p>
<ul>
    <li>first</li>
    <li>second</li>
</ul>
<table>
    <thead>
        <tr><th>Service</th><th>Location</th></tr>
    </thead>
    <tbody>
        <tr><td>Cloud86</td><td>EU</td></tr>
    </tbody>
</table>
<p>See the <a href="/en/legal/dpa">DPA</a>.</p>
HTML;

    $markdown = HtmlToMarkdown::convert($html);

    expect($markdown)
        ->toContain('## Overview')
        ->toContain('**Cloud86**')
        ->toContain('- first')
        ->toContain('| Service | Location |')
        ->toContain('| Cloud86 | EU |')
        ->toContain('[DPA](/en/legal/dpa)');
});
