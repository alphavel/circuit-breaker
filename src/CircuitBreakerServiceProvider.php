<?php

namespace Alphavel\CircuitBreaker;

use Alphavel\ServiceProvider;

class CircuitBreakerServiceProvider extends ServiceProvider
{
    /**
     * Register circuit breaker services
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/circuit-breaker.php', 'circuit-breaker');
        
        // Register CircuitBreakerManager as singleton
        $this->app->singleton('circuit-breaker', function ($app) {
            $config = $app['config']->get('circuit-breaker');
            return new CircuitBreakerManager($config);
        });
        
        // Register facade
        if (class_exists('Alphavel\Facade')) {
            \Alphavel\Facade::register('CircuitBreaker', \Alphavel\CircuitBreaker\Facades\CircuitBreaker::class);
        }
    }

    /**
     * Bootstrap circuit breaker services
     */
    public function boot(): void
    {
        // Publish config
        if (method_exists($this, 'publishes')) {
            $this->publishes([
                __DIR__ . '/../config/circuit-breaker.php' => config_path('circuit-breaker.php'),
            ], 'circuit-breaker-config');
        }
        
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    /**
     * Register console commands
     */
    protected function registerCommands(): void
    {
        $this->app->singleton('command.circuit-breaker.stats', function ($app) {
            return new \Alphavel\CircuitBreaker\Console\StatsCommand($app['circuit-breaker']);
        });
        
        $this->app->singleton('command.circuit-breaker.reset', function ($app) {
            return new \Alphavel\CircuitBreaker\Console\ResetCommand($app['circuit-breaker']);
        });
    }
}
