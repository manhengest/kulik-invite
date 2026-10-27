<?php

/**
 * Copy to config.php and fill in real values.
 * config.php must never be web-accessible (see .htaccess).
 */
return [
    // Public site URL of this landing (no trailing slash)
    'site_url' => 'https://event.example.com',

    // Organizer inbox for Invitation Requests
    'mail_to' => 'rsvp@example.com',
    'mail_from' => 'noreply@example.com',
    'mail_from_name' => 'KULIK SYSTEM Events',

    // SMTP (no Composer — raw AUTH LOGIN over TLS). Empty host = mail stays pending.
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_secure' => 'tls', // tls | ssl | none

    // Kommo CRM (Invitation Leads). Empty crm_url = CRM stays pending.
    // crm_url: https://{subdomain}.kommo.com/api/v4/leads/complex
    // crm_token: long-lived token from private integration → Keys and scopes
    'crm_url' => '',
    'crm_token' => '',
    'kommo_pipeline_id' => 0,
    'kommo_status_id' => 0,
    'kommo_tag' => 'KULIK_EVENT_NOV_03',

    // Telegram stays off unless both are set
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',

    // Paste the same ID into window.KULIK_GTM_ID in index.html
    'gtm_id' => '',

    // Shared secret for cron/retry.php?key=...
    'retry_key' => 'change-me-to-a-long-random-string',

    // Anti-abuse
    'min_fill_seconds' => 3,
    'rate_limit_per_hour' => 8,
];
