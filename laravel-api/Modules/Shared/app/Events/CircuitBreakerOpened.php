<?php

namespace Modules\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CircuitBreakerOpened
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly int $failureCount,
        public readonly int $failureThreshold,
        public readonly int $recoveryTimeout,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service_name' => $this->serviceName,
            'failure_count' => $this->failureCount,
            'failure_threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'context' => $this->context,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
