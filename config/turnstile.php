<?php

return [

    'sitekey' => env('TURNSTILE_SITEKEY', ''),

    'secret' => env('TURNSTILE_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Approved frontend hostnames
    |--------------------------------------------------------------------------
    |
    | Comma-separated hostnames returned by siteverify that this deployment
    | accepts. Production should list only the live host. Local development
    | may also include localhost and 127.0.0.1.
    |
    */

    'hostnames' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TURNSTILE_HOSTNAMES', ''))
    ))),

];
