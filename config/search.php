<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding driver
    |--------------------------------------------------------------------------
    |
    | "hash" builds a local feature-hash vector on the server. It needs no
    | API key and still ranks by cosine similarity. "http" calls an
    | OpenAI-compatible /embeddings endpoint for neural vectors.
    |
    */

    'embeddings' => [
        'driver' => env('SEARCH_EMBEDDINGS_DRIVER', 'hash'),
        'url' => env('SEARCH_EMBEDDINGS_URL'),
        'key' => env('SEARCH_EMBEDDINGS_KEY'),
        'model' => env('SEARCH_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('SEARCH_EMBEDDINGS_DIMENSIONS', 256),
        'timeout' => (int) env('SEARCH_EMBEDDINGS_TIMEOUT', 8),
    ],

    /*
    | Leave null to use the active embedder's default cutoff. A result with
    | no lexical hit is kept only when cosine similarity is at least this.
    |
    */

    'semantic_threshold' => env('SEARCH_SEMANTIC_THRESHOLD'),

    'lexical_weight' => 0.45,

    'semantic_weight' => 0.55,

];
