<?php

namespace Modules\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CircuitBreakerClosedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $serviceName,
        public readonly string $previousState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service_name' => $this->serviceName,
            'previous_state' => $this->previousState,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
