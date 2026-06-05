<?php

namespace App\Actions\Api;

class ParseMarkdownAction
{
    /**
     * Parse markdown to HTML
     */
    public function handle(string $markdown): string
    {
        $html = $markdown;
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        
        // Code blocks
        $html = preg_replace('/```(\w+)?\n(.+?)```/s', '<pre><code>$2</code></pre>', $html);
        
        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        
        // Links
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);
        
        // Line breaks
        $html = nl2br($html);
        
        return $html;
    }
}
