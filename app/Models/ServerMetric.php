<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    protected $fillable = [
        'server_id',
        'cpu_usage',
        'ram_usage',
        'disk_usage',
        'uptime',
    ];

    protected $casts = [
        'cpu_usage' => 'double',
        'ram_usage' => 'double',
        'disk_usage' => 'double',
    ];

    /**
     * Get the server that owns the metrics.
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
