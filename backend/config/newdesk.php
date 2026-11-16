<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'supervisor' => [
        'conf_dir' => env('SUPERVISOR_CONF_DIR', '/etc/supervisor/conf.d'),
        'user' => env('SUPERVISOR_USER', 'www-data'),
        'reverb_port' => (int) env('REVERB_SERVER_PORT', 8080),
    ],

    // RF-13: the assistant runs Claude Code headless (`claude -p`) from the `assistant` queue. Its credentials
    // (CLAUDE_CODE_OAUTH_TOKEN or ANTHROPIC_API_KEY) live in the worker's environment, never in the repo.
    'assistant' => [
        'binary' => env('ASSISTANT_CLAUDE_BINARY', 'claude'),
        // The MCP the turn talks to: this same app, reached locally.
        'mcp_url' => env('ASSISTANT_MCP_URL', rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/mcp'),
        'timeout_seconds' => (int) env('ASSISTANT_TIMEOUT_SECONDS', 180),
        'workdir' => env('ASSISTANT_WORKDIR', storage_path('app/assistant/workdir')),
        'turns_per_hour' => (int) env('ASSISTANT_TURNS_PER_HOUR', 20),
    ],
];
