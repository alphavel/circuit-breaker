<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Default Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default behavior for circuit breakers across your application.
    |
    */

    'default' => 'swoole-table',

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Drivers
    |--------------------------------------------------------------------------
    |
    | Supported: "swoole-table", "redis"
    |
    */

    'drivers' => [
        'swoole-table' => [
            'size' => env('CIRCUIT_BREAKER_SIZE', 10000), // Max services
        ],

        'redis' => [
            'connection' => env('REDIS_CONNECTION', 'default'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Thresholds
    |--------------------------------------------------------------------------
    |
    | Default settings for circuit breaker state transitions.
    |
    */

    'thresholds' => [
        // Number of failures before opening circuit
        'failure_threshold' => env('CIRCUIT_FAILURE_THRESHOLD', 5),
        
        // Success percentage required to close circuit (0-100)
        'success_threshold' => env('CIRCUIT_SUCCESS_THRESHOLD', 80),
        
        // Time window for counting failures (seconds)
        'timeout' => env('CIRCUIT_TIMEOUT', 60),
        
        // Time to wait before half-open attempt (seconds)
        'recovery_timeout' => env('CIRCUIT_RECOVERY_TIMEOUT', 30),
        
        // Number of requests to try in half-open state
        'half_open_requests' => env('CIRCUIT_HALF_OPEN_REQUESTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Metrics
    |--------------------------------------------------------------------------
    |
    | Configure metrics collection and monitoring.
    |
    */

    'metrics' => [
        'enabled' => env('CIRCUIT_METRICS_ENABLED', true),
        'retention' => env('CIRCUIT_METRICS_RETENTION', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Configurations
    |--------------------------------------------------------------------------
    |
    | Configure specific circuit breakers for your services.
    | Each service can have custom thresholds.
    |
    */

    'services' => [
        'payment-api' => [
            'failure_threshold' => 3,
            'timeout' => 30,
            'recovery_timeout' => 60,
        ],
        
        'notification-service' => [
            'failure_threshold' => 10,
            'timeout' => 120,
            'recovery_timeout' => 30,
        ],
        
        // Add your services here...
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Strategies
    |--------------------------------------------------------------------------
    |
    | Define fallback behaviors when circuit is open.
    |
    */

    'fallbacks' => [
        'enabled' => true,
        
        // Default fallback strategy
        'default' => 'exception', // exception, cache, default_value
        
        // Cache fallback settings
        'cache' => [
            'ttl' => 300, // 5 minutes
            'prefix' => 'circuit_breaker_fallback:',
        ],
    ],
];
