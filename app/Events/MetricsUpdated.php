<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetricsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
        public array $metrics
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('server.' . $this->server->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->server->id,
            'cpu_usage' => $this->metrics['cpu_usage'],
            'ram_usage' => $this->metrics['ram_usage'],
            'disk_usage' => $this->metrics['disk_usage'],
            'uptime' => $this->metrics['uptime'] ?? null,
            'is_online' => true,
            'timestamp' => now()->toISOString(),
        ];
    }
}
