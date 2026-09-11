<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    protected $fillable = [
        'server_id',
        'cpu_usage',
        'cpu_iowait',
        'cpu_steal',
        'load_1',
        'load_5',
        'load_15',
        'ram_usage',
        'swap_usage',
        'zombie_processes',
        'process_count',
        'disk_usage',
        'disk_free_gb',
        'inode_usage',
        'network_rx_bytes',
        'network_tx_bytes',
        'uptime',
    ];

    protected $casts = [
        'cpu_usage' => 'double',
        'cpu_iowait' => 'double',
        'cpu_steal' => 'double',
        'load_1' => 'double',
        'load_5' => 'double',
        'load_15' => 'double',
        'ram_usage' => 'double',
        'swap_usage' => 'double',
        'zombie_processes' => 'integer',
        'process_count' => 'integer',
        'disk_usage' => 'double',
        'disk_free_gb' => 'double',
        'inode_usage' => 'double',
        'network_rx_bytes' => 'integer',
        'network_tx_bytes' => 'integer',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
