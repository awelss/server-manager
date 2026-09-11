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
        return [new PrivateChannel('server.'.$this->server->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->server->id,
            'cpu_usage' => $this->metrics['cpu_usage'],
            'cpu_iowait' => $this->metrics['cpu_iowait'] ?? null,
            'cpu_steal' => $this->metrics['cpu_steal'] ?? null,
            'load_1' => $this->metrics['load_1'] ?? null,
            'load_5' => $this->metrics['load_5'] ?? null,
            'load_15' => $this->metrics['load_15'] ?? null,
            'ram_usage' => $this->metrics['ram_usage'],
            'swap_usage' => $this->metrics['swap_usage'] ?? null,
            'zombie_processes' => $this->metrics['zombie_processes'] ?? null,
            'process_count' => $this->metrics['process_count'] ?? null,
            'disk_usage' => $this->metrics['disk_usage'],
            'disk_free_gb' => $this->metrics['disk_free_gb'] ?? null,
            'inode_usage' => $this->metrics['inode_usage'] ?? null,
            'network_rx_bytes' => $this->metrics['network_rx_bytes'] ?? null,
            'network_tx_bytes' => $this->metrics['network_tx_bytes'] ?? null,
            'uptime' => $this->metrics['uptime'] ?? null,
            'is_online' => true,
            'timestamp' => now()->toISOString(),
        ];
    }
}
