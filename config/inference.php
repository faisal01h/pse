<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report inference
    |--------------------------------------------------------------------------
    |
    | "local" reads cue words and known names on this server. "http" calls an
    | OpenAI-compatible chat endpoint and falls back to the local reader when
    | the call fails. Proposals stay hidden until an admin accepts them.
    |
    */

    'driver' => env('INFERENCE_DRIVER', 'local'),

    'url' => env('INFERENCE_URL'),

    'key' => env('INFERENCE_KEY'),

    'model' => env('INFERENCE_MODEL', 'gpt-4o-mini'),

    'timeout' => (int) env('INFERENCE_TIMEOUT', 8),

];
