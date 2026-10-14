<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public read limits
    |--------------------------------------------------------------------------
    |
    | These caps slow a full copy of the catalog. A person browsing stays
    | under them. Limits are counted per IP address.
    |
    */

    'per_minute' => (int) env('CATALOG_PER_MINUTE', 60),
    'per_hour' => (int) env('CATALOG_PER_HOUR', 500),

    'api_per_minute' => (int) env('CATALOG_API_PER_MINUTE', 20),
    'api_per_hour' => (int) env('CATALOG_API_PER_HOUR', 200),

    'attachments_per_minute' => (int) env('CATALOG_ATTACHMENTS_PER_MINUTE', 120),
    'attachments_per_hour' => (int) env('CATALOG_ATTACHMENTS_PER_HOUR', 800),

    'search_per_minute' => (int) env('CATALOG_SEARCH_PER_MINUTE', 90),
    'search_per_hour' => (int) env('CATALOG_SEARCH_PER_HOUR', 400),

    /*
    |--------------------------------------------------------------------------
    | Page sizes
    |--------------------------------------------------------------------------
    |
    | List routes ignore a client page size and always use these.
    |
    */

    'politicians_per_page' => 24,
    'parties_per_page' => 24,
    'businesses_per_page' => 24,
    'reports_per_page' => 20,
    'members_per_page' => 12,
    'api_per_page' => 20,

];
