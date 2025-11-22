<?php

namespace Alphavel\CircuitBreaker\Console;

use Alphavel\Console\Command;
use Alphavel\CircuitBreaker\CircuitBreakerManager;

/**
 * Show circuit breaker statistics
 */
class StatsCommand extends Command
{
    protected string $signature = 'circuit-breaker:stats {service?}';
    protected string $description = 'Show circuit breaker statistics';
    
    protected CircuitBreakerManager $manager;

    public function __construct(CircuitBreakerManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $service = $this->argument('service');
        
        if ($service) {
            $this->showServiceStats($service);
        } else {
            $this->showAllStats();
        }
        
        return 0;
    }

    protected function showServiceStats(string $service): void
    {
        $stats = $this->manager->getStats($service);
        
        $this->info("Circuit Breaker: {$service}");
        $this->line('');
        $this->line("  State: " . strtoupper($stats['state']));
        $this->line("  Failures: {$stats['failures']}");
        $this->line("  Successes: {$stats['successes']}");
        $this->line("  Success Rate: {$stats['success_rate']}%");
        $this->line("  Total Requests: {$stats['total_requests']}");
        $this->line("  Total Failures: {$stats['total_failures']}");
        $this->line("  Total Successes: {$stats['total_successes']}");
        
        if ($stats['opened_at']) {
            $this->line("  Opened At: " . date('Y-m-d H:i:s', $stats['opened_at']));
        }
        
        if ($stats['last_failure_time']) {
            $this->line("  Last Failure: " . date('Y-m-d H:i:s', $stats['last_failure_time']));
        }
        
        if ($stats['last_success_time']) {
            $this->line("  Last Success: " . date('Y-m-d H:i:s', $stats['last_success_time']));
        }
    }

    protected function showAllStats(): void
    {
        $allStats = $this->manager->getAllStats();
        
        if (empty($allStats)) {
            $this->info('No circuit breakers registered yet.');
            return;
        }
        
        $this->info('Circuit Breaker Statistics');
        $this->line('');
        
        foreach ($allStats as $service => $stats) {
            $stateColor = match ($stats['state']) {
                'closed' => 'green',
                'half_open' => 'yellow',
                'open' => 'red',
            };
            
            $state = strtoupper($stats['state']);
            $this->line("<fg={$stateColor}>{$service}: {$state}</> (failures: {$stats['failures']}, success rate: {$stats['success_rate']}%)");
        }
    }
}
