<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('server.{serverId}', function ($user, $serverId) {
    return $user->servers()->where('id', $serverId)->exists();
});

Broadcast::channel('server-logs.{serverId}', function ($user, $serverId) {
    return $user->servers()->where('id', $serverId)->exists();
});
