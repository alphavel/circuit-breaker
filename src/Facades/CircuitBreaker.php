<?php

namespace Alphavel\CircuitBreaker\Facades;

use Alphavel\Facade;
use Alphavel\CircuitBreaker\States\CircuitState;

/**
 * Circuit Breaker facade
 * 
 * @method static mixed call(string $service, callable $callable, mixed $fallback = null)
 * @method static \Alphavel\CircuitBreaker\CircuitBreaker breaker()
 * @method static CircuitState getState(string $service)
 * @method static array getStats(string $service)
 * @method static array getAllStats()
 * @method static void open(string $service)
 * @method static void close(string $service)
 * @method static void reset(string $service)
 * 
 * @package Alphavel\CircuitBreaker\Facades
 */
class CircuitBreaker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'circuit-breaker';
    }
}
