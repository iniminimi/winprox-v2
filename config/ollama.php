<?php

return [
    'enabled' => (bool) env('OLLAMA_ENABLED', true),

    'url' => rtrim((string) env('OLLAMA_URL', 'http://localhost:11434'), '/'),

    'model' => (string) env('OLLAMA_MODEL', 'llama3.1'),

    'timeout' => (int) env('OLLAMA_TIMEOUT', 60),

    'batch_limit' => (int) env('OLLAMA_TRANSLATION_BATCH_LIMIT', 25),
];
