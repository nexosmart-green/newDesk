<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'supervisor' => [
        'conf_dir' => env('SUPERVISOR_CONF_DIR', '/etc/supervisor/conf.d'),
        'user' => env('SUPERVISOR_USER', 'www-data'),
        'reverb_port' => (int) env('REVERB_SERVER_PORT', 8080),
    ],
];
