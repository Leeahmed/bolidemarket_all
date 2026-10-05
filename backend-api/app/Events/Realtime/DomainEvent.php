<?php

namespace App\Events\Realtime;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Str;

abstract class DomainEvent implements ShouldBroadcastNow
{
    public readonly array $payload;

    public function __construct(private array $channels, array $data)
    {
        $this->payload = ['event_id' => (string) Str::uuid(), 'type' => class_basename(static::class), 'occurred_at' => now()->toISOString(), 'data' => $data];
    }

    public function broadcastOn(): array
    {
        return $this->channels;
    }

    public function broadcastAs(): string
    {
        return class_basename(static::class);
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
