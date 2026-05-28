<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class ServerLog extends Model
{
    use Prunable;

    protected $fillable = [
        'server_id',
        'source_file',
        'level',
        'message',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(7));
    }
}
