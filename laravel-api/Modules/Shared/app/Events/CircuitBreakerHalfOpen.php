<?php

namespace Modules\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CircuitBreakerHalfOpen
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly int $successThreshold,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service_name' => $this->serviceName,
            'success_threshold' => $this->successThreshold,
            'context' => $this->context,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
