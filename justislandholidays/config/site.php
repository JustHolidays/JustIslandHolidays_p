<?php

return [

    /*
    | The main address, and the other hosts that redirect to it (301). Choosing between
    | justislandholidays.uk and .co.uk is a one-line change to SITE_CANONICAL_HOST.
    | Leave empty locally and on staging.
    */
    'canonical_host' => env('SITE_CANONICAL_HOST', ''),
    'redirect_hosts' => array_filter(explode(',', env('SITE_REDIRECT_HOSTS',
        'justislandholidays.uk,www.justislandholidays.uk,justislandholidays.co.uk,www.justislandholidays.co.uk'))),

    /*
    | Behind the host's CDN or load balancer, its addresses (comma-separated, or *) so https and
    | the visitor's address are read correctly. Empty by default: trusting every proxy would let
    | anyone fake their address and slip past the enquiry rate limit.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    // Shown on the contact page and in the privacy notice. Set before launch.
    'contact_email' => env('SITE_CONTACT_EMAIL'),

    /*
    | Languages. Every public route carries a /{lang} prefix. Only the enabled
    | languages are served; Spanish and German are switched on in phase 2.
    */
    'languages' => ['en', 'es', 'de'],
    'enabled_languages' => explode(',', env('SITE_LANGUAGES', 'en')),

    // Search results per page, and featured listings shown above them per page.
    'per_page' => 24,
    'featured_per_page' => 3,

    // Similar listings strip on the listing page.
    'similar_count' => 4,

    /*
    | Visitor IP addresses are never stored. They are hashed with this secret and
    | the date, so a hash changes every day and cannot be reversed.
    */
    'ip_hash_salt' => env('IP_HASH_SALT', env('APP_KEY')),

    // Moderation target: listings waiting longer than this are highlighted.
    'review_target_hours' => 48,

    'enquiries' => [
        'recent_days' => 7,          // proposed: flagged as recent in the admin Enquiries list
        'per_ip_per_hour' => 5,
        'retention_months' => 24,   // proposed
    ],

    'clicks' => [
        'unique_window_minutes' => 30,   // proposed
        'per_ip_per_minute' => 20,       // above this, clicks are flagged as bots
        'retention_months' => 13,        // proposed; daily totals are kept
        'rollup_days' => 3,              // nightly rebuild of the last few days' totals, to catch late events
    ],

    // Soft-deleted accounts have their personal data removed after this many days (proposed).
    'deleted_account_purge_days' => 30,

    'deeplink_check' => [
        'per_run' => 500,                // links checked each night, longest-unchecked first
        'pause_ms' => 250,               // pause between requests, to be polite to booking sites
    ],

    /*
    | On a host with cron but no queue supervisor, set QUEUE_VIA_SCHEDULER=true and the
    | scheduler works through the queue every minute (photos, enquiry emails, notices).
    */
    'queue_via_scheduler' => (bool) env('QUEUE_VIA_SCHEDULER', false),

    'altcha' => [
        'hmac_key' => env('ALTCHA_HMAC_KEY', env('APP_KEY')),
        // Work for the visitor's browser: PBKDF2 iterations × a secret counter it must find.
        'cost' => (int) env('ALTCHA_COST', 5000),
        'counter_min' => (int) env('ALTCHA_COUNTER_MIN', 5000),
        'counter_max' => (int) env('ALTCHA_COUNTER_MAX', 10000),
        'expires_minutes' => 20,
    ],

    // Default public colours. The Appearance page (super admin) overrides them via site_settings 'theme.colours'.
    'default_colours' => [
        'brand' => '#0b4f6c',
        'button' => '#0b4f6c',
        'background' => '#ffffff',
        'band' => '#f1f5f4',
        'text' => '#1f2933',
    ],
];
