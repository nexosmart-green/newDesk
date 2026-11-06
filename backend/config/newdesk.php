<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // monitoreo's ENCRYPTION_KEY, to open its agent tokens on import (CA-13.24).
    'monitoreo' => [
        'encryption_key' => env('MONITOREO_ENCRYPTION_KEY'),
    ],

    'monitoring' => [
        // Some origins block bot-like agents in nginx (403/444); empty = monitoreo's default.
        'sitecheck_user_agent' => env('SITECHECK_USER_AGENT'),

        // CA-13.20: while newDesk runs next to monitoreo nothing is sent (client mails, domain notices).
        'notify' => (bool) env('MONITORING_NOTIFY', false),

        // The team's address for the domain expiry notices (monitoreo's ALERT_EMAIL_TO).
        'alert_email' => env('MONITORING_ALERT_EMAIL'),

        // Optional: without it PageSpeed Insights applies Google's shared quota (D-043). Sent in a header.
        'pagespeed_api_key' => env('PAGESPEED_API_KEY'),
    ],

    'supervisor' => [
        'conf_dir' => env('SUPERVISOR_CONF_DIR', '/etc/supervisor/conf.d'),
        'user' => env('SUPERVISOR_USER', 'www-data'),
        'reverb_port' => (int) env('REVERB_SERVER_PORT', 8080),
    ],
];
