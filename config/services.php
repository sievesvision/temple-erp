<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    // Test and Live key pairs both live here side by side; which one is actually used
    // is decided at runtime by the "stripe_mode" Setting (Admin > Settings > Donations
    // & Payments), resolved via App\Services\StripeConfigService — see that class rather
    // than reading config('services.stripe.*') directly anywhere in application code.
    'stripe' => [
        'test_key' => env('STRIPE_TEST_KEY'),
        'test_secret' => env('STRIPE_TEST_SECRET'),
        'test_webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        'live_key' => env('STRIPE_LIVE_KEY'),
        'live_secret' => env('STRIPE_LIVE_SECRET'),
        'live_webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
    ],

    // Sandbox and Live credential pairs both live here side by side; which one is
    // actually used is decided at runtime by the "linkly_mode" Setting, resolved via
    // App\Services\LinklyConfigService — see that class rather than reading
    // config('services.linkly.*') directly anywhere in application code. Unlike Stripe,
    // these are the Cloud EFT Client username/password used only to *pair* a PIN pad —
    // the resulting pairing secret (one per mode) is stored as a Setting, not here.
    'linkly' => [
        'sandbox_username' => env('LINKLY_SANDBOX_USERNAME'),
        'sandbox_password' => env('LINKLY_SANDBOX_PASSWORD'),
        'live_username' => env('LINKLY_LIVE_USERNAME'),
        'live_password' => env('LINKLY_LIVE_PASSWORD'),
    ],

    // mx51 "Simple Cloud Integration" (SCI) — powers the CBA Smart Terminal payment option.
    // The Pairing API Key and Signing Secret Part A are the merchant-held half of every
    // signed SCI request; per mx51's own security requirement they must never be visible,
    // configurable, or editable anywhere in the POS UI, so — same as Linkly above — they
    // live only here, resolved via App\Services\CbaSciConfigService, never read directly
    // nor exposed through any admin form. The per-pairing Signing Secret Part B (returned
    // by mx51 for each individual terminal pairing) is the only piece that has to live in
    // the database, on eft_terminals.sci_signing_secret_part_b, encrypted at rest.
    'cba_sci' => [
        'test_pairing_api_key' => env('CBA_SCI_TEST_PAIRING_API_KEY'),
        'test_signing_secret_part_a' => env('CBA_SCI_TEST_SIGNING_SECRET_PART_A'),
        'live_pairing_api_key' => env('CBA_SCI_LIVE_PAIRING_API_KEY'),
        'live_signing_secret_part_a' => env('CBA_SCI_LIVE_SIGNING_SECRET_PART_A'),
    ],

    // Google reCAPTCHA v2 ("I'm not a robot") keys — get a pair from
    // https://www.google.com/recaptcha/admin for this site's domain(s). Whether the
    // widget actually shows up on login/registration/donation forms is a separate
    // Setting ("recaptcha_enabled", Admin > Settings > Security) so it can be added to
    // .env ahead of time and switched on only once verified — see App\Services\
    // RecaptchaService rather than reading config('services.recaptcha.*') directly.
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

];
