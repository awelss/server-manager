<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->string('agent_token')->unique()->index();
            $table->string('status')->default('pending'); // pending, online, offline
            $table->string('os_info')->nullable();
            $table->integer('cpu_cores')->nullable();
            $table->double('ram_total')->nullable(); // in GB
            $table->double('disk_total')->nullable(); // in GB
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
