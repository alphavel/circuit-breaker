<?php

namespace Alphavel\CircuitBreaker\Drivers;

use Swoole\Table;
use Alphavel\CircuitBreaker\States\CircuitState;

/**
 * High-performance Swoole Table driver for circuit breaker state
 * 
 * Uses lock-free shared memory for O(1) state lookups
 * 
 * @package Alphavel\CircuitBreaker\Drivers
 */
class SwooleTableDriver
{
    protected Table $state;
    protected Table $metrics;

    public function __construct(int $maxServices = 10000)
    {
        // State table: service => state info
        $this->state = new Table($maxServices);
        $this->state->column('state', Table::TYPE_STRING, 16);
        $this->state->column('failures', Table::TYPE_INT, 4);
        $this->state->column('successes', Table::TYPE_INT, 4);
        $this->state->column('opened_at', Table::TYPE_INT, 8);
        $this->state->column('last_attempt', Table::TYPE_INT, 8);
        $this->state->create();
        
        // Metrics table: service => aggregated metrics
        $this->metrics = new Table($maxServices);
        $this->metrics->column('total_requests', Table::TYPE_INT, 8);
        $this->metrics->column('total_failures', Table::TYPE_INT, 8);
        $this->metrics->column('total_successes', Table::TYPE_INT, 8);
        $this->metrics->column('last_failure_time', Table::TYPE_INT, 8);
        $this->metrics->column('last_success_time', Table::TYPE_INT, 8);
        $this->metrics->create();
    }

    /**
     * Get current state for service
     */
    public function getState(string $service): CircuitState
    {
        $data = $this->state->get($service);
        
        if (!$data) {
            return CircuitState::CLOSED; // Default closed
        }
        
        return CircuitState::from($data['state']);
    }

    /**
     * Set state for service
     */
    public function setState(string $service, CircuitState $state): void
    {
        $existing = $this->state->get($service) ?: [];
        
        $this->state->set($service, array_merge($existing, [
            'state' => $state->value,
            'opened_at' => $state === CircuitState::OPEN ? time() : ($existing['opened_at'] ?? 0),
            'last_attempt' => time(),
        ]));
    }

    /**
     * Record failure
     */
    public function recordFailure(string $service): void
    {
        $data = $this->state->get($service) ?: [
            'state' => CircuitState::CLOSED->value,
            'failures' => 0,
            'successes' => 0,
            'opened_at' => 0,
            'last_attempt' => 0,
        ];
        
        $data['failures']++;
        $data['last_attempt'] = time();
        
        $this->state->set($service, $data);
        
        // Update metrics
        $this->updateMetrics($service, false);
    }

    /**
     * Record success
     */
    public function recordSuccess(string $service): void
    {
        $data = $this->state->get($service) ?: [
            'state' => CircuitState::CLOSED->value,
            'failures' => 0,
            'successes' => 0,
            'opened_at' => 0,
            'last_attempt' => 0,
        ];
        
        $data['successes']++;
        $data['last_attempt'] = time();
        
        $this->state->set($service, $data);
        
        // Update metrics
        $this->updateMetrics($service, true);
    }

    /**
     * Reset counters
     */
    public function resetCounters(string $service): void
    {
        $data = $this->state->get($service);
        
        if ($data) {
            $data['failures'] = 0;
            $data['successes'] = 0;
            $this->state->set($service, $data);
        }
    }

    /**
     * Get failure count
     */
    public function getFailures(string $service): int
    {
        $data = $this->state->get($service);
        return $data ? $data['failures'] : 0;
    }

    /**
     * Get success count
     */
    public function getSuccesses(string $service): int
    {
        $data = $this->state->get($service);
        return $data ? $data['successes'] : 0;
    }

    /**
     * Get time when circuit was opened
     */
    public function getOpenedAt(string $service): ?int
    {
        $data = $this->state->get($service);
        return $data && $data['opened_at'] > 0 ? $data['opened_at'] : null;
    }

    /**
     * Get last attempt time
     */
    public function getLastAttempt(string $service): ?int
    {
        $data = $this->state->get($service);
        return $data && $data['last_attempt'] > 0 ? $data['last_attempt'] : null;
    }

    /**
     * Get all services
     */
    public function getAllServices(): array
    {
        $services = [];
        
        foreach ($this->state as $service => $data) {
            $services[] = $service;
        }
        
        return $services;
    }

    /**
     * Get service stats
     */
    public function getStats(string $service): array
    {
        $stateData = $this->state->get($service);
        $metricsData = $this->metrics->get($service);
        
        return [
            'service' => $service,
            'state' => $stateData ? CircuitState::from($stateData['state'])->value : 'closed',
            'failures' => $stateData['failures'] ?? 0,
            'successes' => $stateData['successes'] ?? 0,
            'opened_at' => $stateData['opened_at'] ?? null,
            'last_attempt' => $stateData['last_attempt'] ?? null,
            'total_requests' => $metricsData['total_requests'] ?? 0,
            'total_failures' => $metricsData['total_failures'] ?? 0,
            'total_successes' => $metricsData['total_successes'] ?? 0,
            'success_rate' => $this->calculateSuccessRate($service),
            'last_failure_time' => $metricsData['last_failure_time'] ?? null,
            'last_success_time' => $metricsData['last_success_time'] ?? null,
        ];
    }

    /**
     * Update metrics
     */
    protected function updateMetrics(string $service, bool $success): void
    {
        $data = $this->metrics->get($service) ?: [
            'total_requests' => 0,
            'total_failures' => 0,
            'total_successes' => 0,
            'last_failure_time' => 0,
            'last_success_time' => 0,
        ];
        
        $data['total_requests']++;
        
        if ($success) {
            $data['total_successes']++;
            $data['last_success_time'] = time();
        } else {
            $data['total_failures']++;
            $data['last_failure_time'] = time();
        }
        
        $this->metrics->set($service, $data);
    }

    /**
     * Calculate success rate
     */
    protected function calculateSuccessRate(string $service): float
    {
        $data = $this->metrics->get($service);
        
        if (!$data || $data['total_requests'] === 0) {
            return 100.0;
        }
        
        return round(($data['total_successes'] / $data['total_requests']) * 100, 2);
    }

    /**
     * Clear all data
     */
    public function clear(): void
    {
        foreach ($this->state as $service => $_) {
            $this->state->del($service);
        }
        
        foreach ($this->metrics as $service => $_) {
            $this->metrics->del($service);
        }
    }
}
