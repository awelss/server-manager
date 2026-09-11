<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->double('cpu_iowait')->nullable()->after('cpu_usage');
            $table->double('cpu_steal')->nullable()->after('cpu_iowait');
            $table->double('load_1')->nullable()->after('cpu_steal');
            $table->double('load_5')->nullable()->after('load_1');
            $table->double('load_15')->nullable()->after('load_5');
            $table->double('swap_usage')->nullable()->after('ram_usage');
            $table->unsignedInteger('zombie_processes')->nullable()->after('swap_usage');
            $table->unsignedInteger('process_count')->nullable()->after('zombie_processes');
            $table->double('disk_free_gb')->nullable()->after('disk_usage');
            $table->double('inode_usage')->nullable()->after('disk_free_gb');
            $table->unsignedBigInteger('network_rx_bytes')->nullable()->after('inode_usage');
            $table->unsignedBigInteger('network_tx_bytes')->nullable()->after('network_rx_bytes');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->json('service_status')->nullable()->after('last_seen_at');
            $table->json('docker_status')->nullable()->after('service_status');
            $table->json('backup_status')->nullable()->after('docker_status');
            $table->json('http_checks')->nullable()->after('backup_status');
            $table->timestamp('status_checked_at')->nullable()->after('http_checks');
        });

        Schema::table('alert_rules', function (Blueprint $table) {
            $table->unsignedInteger('for_minutes')->default(5)->after('threshold');
            $table->boolean('recovery_enabled')->default(true)->after('cooldown_minutes');
            $table->timestamp('breach_started_at')->nullable()->after('last_triggered_at');
            $table->boolean('is_active')->default(false)->after('breach_started_at');
            $table->timestamp('last_recovered_at')->nullable()->after('is_active');
            $table->double('last_value')->nullable()->after('last_recovered_at');
        });
    }

    public function down(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->dropColumn([
                'for_minutes',
                'recovery_enabled',
                'breach_started_at',
                'is_active',
                'last_recovered_at',
                'last_value',
            ]);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'service_status',
                'docker_status',
                'backup_status',
                'http_checks',
                'status_checked_at',
            ]);
        });

        Schema::table('server_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'cpu_iowait',
                'cpu_steal',
                'load_1',
                'load_5',
                'load_15',
                'swap_usage',
                'zombie_processes',
                'process_count',
                'disk_free_gb',
                'inode_usage',
                'network_rx_bytes',
                'network_tx_bytes',
            ]);
        });
    }
};
