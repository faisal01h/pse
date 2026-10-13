<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report inference
    |--------------------------------------------------------------------------
    |
    | "local" reads cue words and known names on this server. "http" calls an
    | OpenAI-compatible chat endpoint. "laya" asks laya-serve through LayaPHP
    | (LAYA_URL and LAYA_API_KEY). Both remote drivers fall back to the local
    | reader when the call fails. Proposals stay hidden until an admin accepts
    | them.
    |
    */

    'driver' => env('INFERENCE_DRIVER', 'local'),

    'url' => env('INFERENCE_URL'),

    'key' => env('INFERENCE_KEY'),

    'model' => env('INFERENCE_MODEL', 'gpt-4o-mini'),

    'timeout' => (int) env('INFERENCE_TIMEOUT', 8),

    'laya_min_confidence' => (float) env('INFERENCE_LAYA_MIN_CONFIDENCE', 0.55),

];
