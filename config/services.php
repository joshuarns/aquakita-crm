<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Cloudflare Turnstile (CAPTCHA invisible para los formularios públicos).
    // Si ambas llaves están vacías, Turnstile queda deshabilitado.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Buzón de conversación con leads (responder por correo + leer respuestas por IMAP).
    // Si 'address' está vacío, las respuestas salen desde el remitente de la empresa
    // y no se tokeniza el Reply-To (las entrantes se casan solo por correo del lead).
    'leads_mailbox' => [
        'address' => env('LEADS_MAILBOX_ADDRESS'),            // p. ej. leads@proyectopublicitario.com.mx
        'imap_host' => env('LEADS_IMAP_HOST', 'localhost'),
        'imap_port' => (int) env('LEADS_IMAP_PORT', 993),
        'imap_username' => env('LEADS_IMAP_USERNAME'),        // normalmente = address
        'imap_password' => env('LEADS_IMAP_PASSWORD'),
        'imap_encryption' => env('LEADS_IMAP_ENCRYPTION', 'ssl'),
        'imap_folder' => env('LEADS_IMAP_FOLDER', 'INBOX'),
        'imap_validate_cert' => (bool) env('LEADS_IMAP_VALIDATE_CERT', false),
    ],

];
