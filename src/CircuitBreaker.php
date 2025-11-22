<?php

namespace Alphavel\CircuitBreaker;

use Alphavel\CircuitBreaker\States\CircuitState;
use Alphavel\CircuitBreaker\Drivers\SwooleTableDriver;
use Alphavel\CircuitBreaker\Exceptions\CircuitOpenException;

/**
 * Circuit Breaker implementation
 * 
 * Implements Circuit Breaker pattern for resilient microservices
 * 
 * Performance: < 0.1ms overhead per call
 * 
 * @package Alphavel\CircuitBreaker
 */
class CircuitBreaker
{
    protected SwooleTableDriver $driver;
    protected array $config;

    public function __construct(SwooleTableDriver $driver, array $config = [])
    {
        $this->driver = $driver;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Execute callable with circuit breaker protection
     * 
     * @throws CircuitOpenException
     */
    public function call(string $service, callable $callable, mixed $fallback = null): mixed
    {
        // Check circuit state
        if (!$this->canAttempt($service)) {
            // Circuit is open, use fallback if available
            if ($fallback !== null) {
                return is_callable($fallback) ? $fallback() : $fallback;
            }
            
            throw new CircuitOpenException($service, $this->driver->getStats($service));
        }
        
        try {
            $result = $callable();
            $this->onSuccess($service);
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($service);
            throw $e;
        }
    }

    /**
     * Check if request can be attempted
     */
    protected function canAttempt(string $service): bool
    {
        $state = $this->driver->getState($service);
        
        return match ($state) {
            CircuitState::CLOSED => true,
            CircuitState::OPEN => $this->shouldAttemptRecovery($service),
            CircuitState::HALF_OPEN => $this->canAttemptHalfOpen($service),
        };
    }

    /**
     * Check if should attempt recovery from OPEN state
     */
    protected function shouldAttemptRecovery(string $service): bool
    {
        $openedAt = $this->driver->getOpenedAt($service);
        
        if (!$openedAt) {
            return true;
        }
        
        $recoveryTimeout = $this->getServiceConfig($service, 'recovery_timeout');
        $elapsed = time() - $openedAt;
        
        if ($elapsed >= $recoveryTimeout) {
            // Move to HALF_OPEN
            $this->driver->setState($service, CircuitState::HALF_OPEN);
            $this->driver->resetCounters($service);
            return true;
        }
        
        return false;
    }

    /**
     * Check if can attempt in HALF_OPEN state
     */
    protected function canAttemptHalfOpen(string $service): bool
    {
        $attempts = $this->driver->getSuccesses($service) + $this->driver->getFailures($service);
        $maxAttempts = $this->getServiceConfig($service, 'half_open_requests');
        
        return $attempts < $maxAttempts;
    }

    /**
     * Handle successful request
     */
    protected function onSuccess(string $service): void
    {
        $this->driver->recordSuccess($service);
        
        $state = $this->driver->getState($service);
        
        if ($state === CircuitState::HALF_OPEN) {
            // Check if enough successes to close circuit
            if ($this->shouldCloseCircuit($service)) {
                $this->driver->setState($service, CircuitState::CLOSED);
                $this->driver->resetCounters($service);
            }
        } elseif ($state === CircuitState::CLOSED) {
            // Reset failures on success
            $this->resetIfTimeout($service);
        }
    }

    /**
     * Handle failed request
     */
    protected function onFailure(string $service): void
    {
        $this->driver->recordFailure($service);
        
        $state = $this->driver->getState($service);
        
        if ($state === CircuitState::HALF_OPEN) {
            // Any failure in HALF_OPEN reopens circuit
            $this->driver->setState($service, CircuitState::OPEN);
            $this->driver->resetCounters($service);
        } elseif ($state === CircuitState::CLOSED) {
            // Check if should open circuit
            if ($this->shouldOpenCircuit($service)) {
                $this->driver->setState($service, CircuitState::OPEN);
            }
        }
    }

    /**
     * Check if circuit should be opened
     */
    protected function shouldOpenCircuit(string $service): bool
    {
        $failures = $this->driver->getFailures($service);
        $threshold = $this->getServiceConfig($service, 'failure_threshold');
        
        return $failures >= $threshold;
    }

    /**
     * Check if circuit should be closed
     */
    protected function shouldCloseCircuit(string $service): bool
    {
        $successes = $this->driver->getSuccesses($service);
        $failures = $this->driver->getFailures($service);
        $total = $successes + $failures;
        
        if ($total === 0) {
            return false;
        }
        
        $successRate = ($successes / $total) * 100;
        $threshold = $this->getServiceConfig($service, 'success_threshold');
        
        return $successRate >= $threshold;
    }

    /**
     * Reset failure counter if timeout reached
     */
    protected function resetIfTimeout(string $service): void
    {
        $lastAttempt = $this->driver->getLastAttempt($service);
        
        if (!$lastAttempt) {
            return;
        }
        
        $timeout = $this->getServiceConfig($service, 'timeout');
        $elapsed = time() - $lastAttempt;
        
        if ($elapsed >= $timeout) {
            $this->driver->resetCounters($service);
        }
    }

    /**
     * Get circuit state
     */
    public function getState(string $service): CircuitState
    {
        return $this->driver->getState($service);
    }

    /**
     * Get circuit statistics
     */
    public function getStats(string $service): array
    {
        return $this->driver->getStats($service);
    }

    /**
     * Get all services
     */
    public function getAllServices(): array
    {
        return $this->driver->getAllServices();
    }

    /**
     * Get all services stats
     */
    public function getAllStats(): array
    {
        $stats = [];
        
        foreach ($this->getAllServices() as $service) {
            $stats[$service] = $this->getStats($service);
        }
        
        return $stats;
    }

    /**
     * Manually open circuit
     */
    public function open(string $service): void
    {
        $this->driver->setState($service, CircuitState::OPEN);
    }

    /**
     * Manually close circuit
     */
    public function close(string $service): void
    {
        $this->driver->setState($service, CircuitState::CLOSED);
        $this->driver->resetCounters($service);
    }

    /**
     * Manually reset circuit
     */
    public function reset(string $service): void
    {
        $this->close($service);
    }

    /**
     * Get service-specific config
     */
    protected function getServiceConfig(string $service, string $key): mixed
    {
        if (isset($this->config['services'][$service][$key])) {
            return $this->config['services'][$service][$key];
        }
        
        return $this->config['thresholds'][$key] ?? $this->getDefaultConfig()['thresholds'][$key];
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'thresholds' => [
                'failure_threshold' => 5,
                'success_threshold' => 80,
                'timeout' => 60,
                'recovery_timeout' => 30,
                'half_open_requests' => 3,
            ],
            'services' => [],
        ];
    }
}
