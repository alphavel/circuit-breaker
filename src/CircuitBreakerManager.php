<?php

namespace Alphavel\CircuitBreaker;

use Alphavel\CircuitBreaker\Drivers\SwooleTableDriver;

/**
 * Circuit Breaker Manager
 * 
 * Manages multiple circuit breaker instances
 * 
 * @package Alphavel\CircuitBreaker
 */
class CircuitBreakerManager
{
    protected SwooleTableDriver $driver;
    protected array $config;
    protected ?CircuitBreaker $circuitBreaker = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->driver = $this->createDriver();
        $this->circuitBreaker = new CircuitBreaker($this->driver, $config);
    }

    /**
     * Create driver instance
     */
    protected function createDriver(): SwooleTableDriver
    {
        $driverName = $this->config['default'] ?? 'swoole-table';
        
        return match ($driverName) {
            'swoole-table' => new SwooleTableDriver(
                $this->config['drivers']['swoole-table']['size'] ?? 10000
            ),
            default => throw new \InvalidArgumentException("Unsupported driver: {$driverName}")
        };
    }

    /**
     * Execute callable with circuit breaker protection
     */
    public function call(string $service, callable $callable, mixed $fallback = null): mixed
    {
        return $this->circuitBreaker->call($service, $callable, $fallback);
    }

    /**
     * Get circuit breaker instance
     */
    public function breaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    /**
     * Get stats for service
     */
    public function getStats(string $service): array
    {
        return $this->circuitBreaker->getStats($service);
    }

    /**
     * Get all stats
     */
    public function getAllStats(): array
    {
        return $this->circuitBreaker->getAllStats();
    }

    /**
     * Get driver instance
     */
    public function getDriver(): SwooleTableDriver
    {
        return $this->driver;
    }
}
