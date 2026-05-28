<?php

namespace App\Events;

use App\Models\Server;
use App\Models\ServerLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LogReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
        public ServerLog $log
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('server-logs.' . $this->server->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->log->id,
            'source_file' => $this->log->source_file,
            'level' => $this->log->level,
            'message' => $this->log->message,
            'logged_at' => $this->log->logged_at->toISOString(),
        ];
    }
}
