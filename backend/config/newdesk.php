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

        // RF-13.09: the twice-daily alert digest (comma-separated; empty = alert_email) and the
        // external heartbeat pinged every minute while the poller is healthy (D-009).
        'digest_email_to' => env('DIGEST_EMAIL_TO'),
        'healthchecks_url' => env('HEALTHCHECKS_URL'),

        // The "Monitoreo" Discord bot (D-059..D-061). Without a token nothing is listed or posted.
        'discord' => [
            'bot_token' => env('DISCORD_BOT_TOKEN'),
            'guild_id' => env('DISCORD_GUILD_ID'),
            // Where new projects get their channel.
            'category_id' => env('DISCORD_CATEGORY_ID'),
            // #resumen: servers down and the digest. Empty: those are not posted.
            'summary_channel_id' => env('DISCORD_SUMMARY_CHANNEL_ID'),
            // The only channel groups the panel lists and posts to (comma-separated ids).
            'channel_categories' => array_values(array_filter(array_map('trim', explode(',', (string) env('DISCORD_CHANNEL_CATEGORIES', ''))))),
        ],

        // "Generar reporte" (D-074): systemd's socket to root's `claude -p`. Without it the button says why.
        'claude_socket' => env('MONITORING_CLAUDE_SOCKET', '/run/monitor-claude.sock'),
    ],

    'supervisor' => [
        'conf_dir' => env('SUPERVISOR_CONF_DIR', '/etc/supervisor/conf.d'),
        'user' => env('SUPERVISOR_USER', 'www-data'),
        'reverb_port' => (int) env('REVERB_SERVER_PORT', 8080),
    ],
];
