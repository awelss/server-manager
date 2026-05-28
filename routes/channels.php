<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('server.{serverId}', function ($user, $serverId) {
    if ($user->isAdmin()) {
        return true;
    }
    return $user->accessibleServers()->where('servers.id', $serverId)->exists();
});

Broadcast::channel('server-logs.{serverId}', function ($user, $serverId) {
    if ($user->isAdmin()) {
        return true;
    }
    return $user->accessibleServers()->where('servers.id', $serverId)->exists();
});
