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
        'service_status',
        'docker_status',
        'backup_status',
        'http_checks',
        'status_checked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'status_checked_at' => 'datetime',
        'cpu_cores' => 'integer',
        'ram_total' => 'double',
        'disk_total' => 'double',
        'service_status' => 'array',
        'docker_status' => 'array',
        'backup_status' => 'array',
        'http_checks' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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

    public function latestMetric()
    {
        return $this->hasOne(ServerMetric::class)->latestOfMany();
    }

    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gt(now()->subSeconds(15));
    }
}
