<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'ip_address',
        'agent_token',
        'status',
        'os_info',
        'cpu_cores',
        'ram_total',
        'disk_total',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'cpu_cores' => 'integer',
        'ram_total' => 'double',
        'disk_total' => 'double',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Users assigned to this server via pivot table.
     */
    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'server_user')->withTimestamps();
    }

    public function logs()
    {
        return $this->hasMany(ServerLog::class);
    }

    public function metrics()
    {
        return $this->hasMany(ServerMetric::class);
    }

    /**
     * Get the latest metric for the server.
     */
    public function latestMetric()
    {
        return $this->hasOne(ServerMetric::class)->latestOfMany();
    }

    /**
     * Determine if the server is currently online.
     * We consider a server online if we've heard from it in the last 15 seconds.
     */
    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gt(now()->subSeconds(15));
    }
}
