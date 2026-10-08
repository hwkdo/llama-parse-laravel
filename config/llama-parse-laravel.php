<?php

return [
    'api_key' => env('LLAMA_CLOUD_API_KEY', env('LLAMA_PARSE_API_KEY')),
    'base_url' => env('LLAMA_PARSE_BASE_URL', 'https://api.cloud.llamaindex.ai'),
    'tier' => env('LLAMA_PARSE_TIER', 'agentic'),
    'version' => env('LLAMA_PARSE_VERSION', 'latest'),
    'poll_interval_ms' => (int) env('LLAMA_PARSE_POLL_INTERVAL_MS', 2000),
    'timeout_seconds' => (int) env('LLAMA_PARSE_TIMEOUT_SECONDS', 300),
];
