<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    | Supported: "groq", "mock"
    | Set AI_PROVIDER=mock in local .env to develop without API credits.
    */
    'provider' => env('AI_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | Decision backend
    |--------------------------------------------------------------------------
    | "php"    — decisions computed by the in-app rule engines / classical ML.
    | "python" — decisions delegated to the ai-research FastAPI model service
    |            (with automatic PHP fallback if it is unreachable).
    */
    'decision_backend'  => env('AI_DECISION_BACKEND', 'php'),
    'inference_url'     => env('AI_INFERENCE_URL', 'http://localhost:8008'),
    'inference_key'     => env('AI_SERVICE_KEY', ''),
    'inference_timeout' => env('AI_INFERENCE_TIMEOUT', 3),

    /*
    |--------------------------------------------------------------------------
    | Response Cache TTL
    |--------------------------------------------------------------------------
    | Identical prompts from the same user are cached for this many seconds.
    | Set to 0 to disable caching.
    */
    'cache_ttl' => env('AI_CACHE_TTL', 7200),

    /*
    |--------------------------------------------------------------------------
    | Queue Driver
    |--------------------------------------------------------------------------
    | "sync"     — run AI calls inline (local / staging)
    | "database" — dispatch to the 'ai' queue (production async mode)
    */
    'queue_driver' => env('AI_QUEUE_DRIVER', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    | Toggle individual AI features per environment via .env.
    */
    'features' => [
        'trip_planner'   => env('AI_TRIP_PLANNER',   true),
        'weather'        => env('AI_WEATHER',         true),
        'gear_assistant' => env('AI_GEAR_ASSISTANT',  true),
        'group_matching'  => env('AI_GROUP_MATCHING',   true),
        'content_gen'     => env('AI_CONTENT_GEN',      false),
        'pricing'         => env('AI_PRICING',           true),
        'safety'          => env('AI_SAFETY',           true),
        'explainability'  => env('AI_EXPLAINABILITY',   true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Weather Integration
    |--------------------------------------------------------------------------
    */
    'weather' => [
        'cache_ttl'    => env('WEATHER_CACHE_TTL', 10800),
        'risk_warning' => env('WEATHER_RISK_WARNING', true),
        'mock_coords'  => ['lat' => 36.95, 'lng' => 8.76], // Tabarka fallback
    ],

];
