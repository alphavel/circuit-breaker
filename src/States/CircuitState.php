<?php

namespace Alphavel\CircuitBreaker\States;

/**
 * Circuit Breaker state enumeration
 * 
 * @package Alphavel\CircuitBreaker\States
 */
enum CircuitState: string
{
    case CLOSED = 'closed';      // Normal operation
    case OPEN = 'open';          // Failing, reject requests
    case HALF_OPEN = 'half_open'; // Testing recovery
}
