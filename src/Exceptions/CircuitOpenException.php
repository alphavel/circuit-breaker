<?php

namespace Alphavel\CircuitBreaker\Exceptions;

use Exception;

/**
 * Exception thrown when circuit is open
 * 
 * @package Alphavel\CircuitBreaker\Exceptions
 */
class CircuitOpenException extends Exception
{
    protected string $serviceName;
    protected array $metrics;

    public function __construct(string $serviceName, array $metrics = [])
    {
        $this->serviceName = $serviceName;
        $this->metrics = $metrics;
        
        parent::__construct("Circuit breaker for '{$serviceName}' is OPEN");
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
