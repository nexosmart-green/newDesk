<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // monitoreo's ENCRYPTION_KEY, to open its agent tokens on import (CA-13.24).
    'monitoreo' => [
        'encryption_key' => env('MONITOREO_ENCRYPTION_KEY'),
    ],

    'supervisor' => [
        'conf_dir' => env('SUPERVISOR_CONF_DIR', '/etc/supervisor/conf.d'),
        'user' => env('SUPERVISOR_USER', 'www-data'),
        'reverb_port' => (int) env('REVERB_SERVER_PORT', 8080),
    ],
];
