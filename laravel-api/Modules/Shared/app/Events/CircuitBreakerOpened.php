<?php

namespace Modules\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CircuitBreakerOpened
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $serviceName,
        public readonly int $failureCount,
        public readonly int $failureThreshold,
        public readonly int $recoveryTimeout,
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
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
