<?php

namespace App\Jobs;

use App\Models\AlertRule;
use App\Models\Server;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckAlertThresholds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Server $server,
        public array $metrics
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $rules = AlertRule::where('enabled', true)
            ->where('user_id', $this->server->user_id)
            ->where(function ($q) {
                $q->whereNull('server_id')
                  ->orWhere('server_id', $this->server->id);
            })
            ->get();

        foreach ($rules as $rule) {
            if ($rule->isInCooldown()) {
                continue;
            }

            $value = match ($rule->metric) {
                'cpu' => $this->metrics['cpu_usage'] ?? null,
                'ram' => $this->metrics['ram_usage'] ?? null,
                'disk' => $this->metrics['disk_usage'] ?? null,
                default => null,
            };

            if ($value === null) {
                continue;
            }

            if ($rule->evaluate((float) $value)) {
                $message = sprintf(
                    "[VPS Alert] %s on *%s* (%s)\n%s is at *%.1f%%* (threshold: %s %.1f%%)",
                    strtoupper($rule->metric),
                    $this->server->name,
                    $this->server->ip_address,
                    ucfirst($rule->metric),
                    $value,
                    $rule->operator,
                    $rule->threshold
                );

                $whatsApp->send($rule->whatsapp_number, $message);
                $rule->update(['last_triggered_at' => now()]);
            }
        }
    }
}
