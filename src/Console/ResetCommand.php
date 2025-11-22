<?php

namespace Alphavel\CircuitBreaker\Console;

use Alphavel\Console\Command;
use Alphavel\CircuitBreaker\CircuitBreakerManager;

/**
 * Reset circuit breaker state
 */
class ResetCommand extends Command
{
    protected string $signature = 'circuit-breaker:reset {service}';
    protected string $description = 'Reset circuit breaker state';
    
    protected CircuitBreakerManager $manager;

    public function __construct(CircuitBreakerManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $service = $this->argument('service');
        
        $this->manager->breaker()->reset($service);
        
        $this->info("Circuit breaker for '{$service}' has been reset.");
        
        return 0;
    }
}
