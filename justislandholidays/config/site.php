<?php

return [

    /*
    | The main address, and the other hosts that redirect to it (301). Decided:
    | justislandholidays.co.uk, so the server sets SITE_CANONICAL_HOST=justislandholidays.co.uk
    | and justislandholidays.uk and the www. hosts redirect to it. Leave empty locally.
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

    /*
    | Keep search engines out (every response says noindex, robots.txt disallows all).
    | On until launch, because the site is already reachable on its real domain.
    | launch:check fails while it is on.
    */
    'block_indexing' => (bool) env('SITE_BLOCK_INDEXING', false),

    // Shown on the contact page and in the privacy notice. Set before launch.
    'contact_email' => env('SITE_CONTACT_EMAIL'),

    /*
    | Languages. Every public route carries a /{lang} prefix. Only the enabled
    | languages are served; Spanish and German are switched on in phase 2.
    */
    'languages' => ['en', 'es', 'de'],
    'enabled_languages' => explode(',', env('SITE_LANGUAGES', 'en')),

    /*
    | Maps (Leaflet). The map images come from a tile provider, which sees the visitor's
    | address when their browser loads them (the privacy notice says so). The default is
    | OpenStreetMap's own server, fine for building but not for a live commercial site:
    | before launch, set MAP_TILE_URL and MAP_TILE_ATTRIBUTION from a provider such as
    | MapTiler or Stadia Maps. No cookies are set either way.
    */
    'map' => [
        'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
        'max_pins' => 200,              // pins returned per map view (spec)
        'area_radius_m' => 400,         // circle on the listing page; the public point is 250 to 350 m from the home

        /*
        | Default map markers, one per kind of listing. A super admin changes the label,
        | colour and optional icon on the Appearance page. White price text sits on the
        | colour, so each colour must reach 4.5 to 1 against white.
        */
        'markers' => [
            'affiliate' => ['label' => 'Partner booking site', 'colour' => '#9a3412'],
            'free' => ['label' => 'Owner listing', 'colour' => '#475569'],
            'paid' => ['label' => 'Premium listing', 'colour' => '#0b4f6c'],
        ],
    ],

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
