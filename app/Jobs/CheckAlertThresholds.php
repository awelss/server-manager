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
            $value = $this->metricValue($rule->metric);

            if ($value === null) {
                continue;
            }

            $value = (float) $value;
            $breaching = $rule->evaluate($value);
            $rule->last_value = $value;

            if (!$breaching) {
                $this->recoverRule($rule, $value, $whatsApp);
                continue;
            }

            if (!$rule->breach_started_at) {
                $rule->breach_started_at = now();
                $rule->save();

                if ($rule->for_minutes > 0) {
                    continue;
                }
            }

            $requiredSeconds = max(0, (int) $rule->for_minutes) * 60;
            $breachSeconds = $rule->breach_started_at
                ? $rule->breach_started_at->diffInSeconds(now())
                : 0;

            if ($breachSeconds < $requiredSeconds) {
                $rule->save();
                continue;
            }

            // First alert after sustained breach, then reminders only after cooldown.
            if (!$rule->is_active || !$rule->isInCooldown()) {
                $whatsApp->send(
                    $rule->whatsapp_number,
                    $this->alertMessage($rule, $value)
                );

                $rule->is_active = true;
                $rule->last_triggered_at = now();
            }

            $rule->save();
        }
    }

    private function metricValue(string $metric): mixed
    {
        return match ($metric) {
            'cpu' => $this->metrics['cpu_usage'] ?? null,
            'ram' => $this->metrics['ram_usage'] ?? null,
            'disk' => $this->metrics['disk_usage'] ?? null,
            'steal' => $this->metrics['cpu_steal'] ?? null,
            'iowait' => $this->metrics['cpu_iowait'] ?? null,
            'swap' => $this->metrics['swap_usage'] ?? null,
            'zombie' => $this->metrics['zombie_processes'] ?? null,
            'processes' => $this->metrics['process_count'] ?? null,
            'load1' => $this->metrics['load_1'] ?? null,
            'inode' => $this->metrics['inode_usage'] ?? null,
            default => null,
        };
    }

    private function recoverRule(AlertRule $rule, float $value, WhatsAppService $whatsApp): void
    {
        if ($rule->is_active && $rule->recovery_enabled) {
            $whatsApp->send(
                $rule->whatsapp_number,
                sprintf(
                    "[VPS Recovery] %s on *%s* (%s) is back to normal at *%s*.",
                    strtoupper($rule->metric),
                    $this->server->name,
                    $this->server->ip_address,
                    $this->formatValue($rule->metric, $value)
                )
            );
            $rule->last_recovered_at = now();
        }

        $rule->is_active = false;
        $rule->breach_started_at = null;
        $rule->save();
    }

    private function alertMessage(AlertRule $rule, float $value): string
    {
        $duration = $rule->for_minutes > 0
            ? " for {$rule->for_minutes} min"
            : '';

        return sprintf(
            "[VPS Alert] %s on *%s* (%s)\n%s is *%s* (threshold: %s %s)%s",
            strtoupper($rule->metric),
            $this->server->name,
            $this->server->ip_address,
            $this->metricLabel($rule->metric),
            $this->formatValue($rule->metric, $value),
            $rule->operator,
            $this->formatValue($rule->metric, (float) $rule->threshold),
            $duration
        );
    }

    private function metricLabel(string $metric): string
    {
        return match ($metric) {
            'cpu' => 'CPU usage',
            'ram' => 'RAM usage',
            'disk' => 'Disk usage',
            'steal' => 'CPU steal',
            'iowait' => 'I/O wait',
            'swap' => 'Swap usage',
            'zombie' => 'Zombie processes',
            'processes' => 'Process count',
            'load1' => 'Load average (1m)',
            'inode' => 'Inode usage',
            default => ucfirst($metric),
        };
    }

    private function formatValue(string $metric, float $value): string
    {
        return match ($metric) {
            'zombie', 'processes' => (string) round($value),
            'load1' => number_format($value, 2),
            default => number_format($value, 1).'%',
        };
    }
}
