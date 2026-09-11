<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $fillable = [
        'user_id',
        'server_id',
        'metric',
        'operator',
        'threshold',
        'for_minutes',
        'cooldown_minutes',
        'recovery_enabled',
        'whatsapp_number',
        'enabled',
        'breach_started_at',
        'is_active',
        'last_recovered_at',
        'last_value',
    ];

    protected $casts = [
        'threshold' => 'double',
        'for_minutes' => 'integer',
        'cooldown_minutes' => 'integer',
        'recovery_enabled' => 'boolean',
        'enabled' => 'boolean',
        'last_triggered_at' => 'datetime',
        'breach_started_at' => 'datetime',
        'is_active' => 'boolean',
        'last_recovered_at' => 'datetime',
        'last_value' => 'double',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function isInCooldown(): bool
    {
        if (!$this->last_triggered_at) {
            return false;
        }

        return $this->last_triggered_at->copy()->addMinutes($this->cooldown_minutes)->isFuture();
    }

    public function evaluate(float $value): bool
    {
        return match ($this->operator) {
            '>' => $value > $this->threshold,
            '>=' => $value >= $this->threshold,
            '<' => $value < $this->threshold,
            '<=' => $value <= $this->threshold,
            default => false,
        };
    }
}
