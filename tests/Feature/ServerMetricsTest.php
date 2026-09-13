<?php

namespace Tests\Feature;

use App\Models\ServerMetric;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServerMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_contains_only_recent_metrics_in_chronological_order(): void
    {
        $this->travelTo(now()->startOfSecond());
        $owner = User::factory()->create();
        $server = $owner->servers()->create(['name' => 'Test', 'ip_address' => '127.0.0.1', 'agent_token' => Str::random(40)]);
        foreach ([now()->subHours(25), now()->subMinutes(5), now()->subMinutes(10)] as $time) {
            $metric = $server->metrics()->make(['cpu_usage' => 20, 'ram_usage' => 30, 'disk_usage' => 40]);
            $metric->created_at = $time;
            $metric->save();
        }
        $response = $this->actingAs($owner)->getJson(route('servers.metrics', $server));
        $response->assertOk()->assertJsonCount(2, 'metrics');
        $this->assertLessThan($response->json('metrics.1.created_at'), $response->json('metrics.0.created_at'));
    }

    public function test_history_is_bounded_and_keeps_the_latest_samples(): void
    {
        $this->travelTo(now()->startOfSecond());
        $owner = User::factory()->create();
        $server = $owner->servers()->create(['name' => 'Test', 'ip_address' => '127.0.0.1', 'agent_token' => Str::random(40)]);
        $rows = [];
        for ($i = 0; $i < 2005; $i++) {
            $rows[] = ['server_id' => $server->id, 'cpu_usage' => 20, 'ram_usage' => 30, 'disk_usage' => 40, 'created_at' => now()->subSeconds(2005 - $i), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            ServerMetric::insert($chunk);
        }
        $response = $this->actingAs($owner)->getJson(route('servers.metrics', $server));
        $response->assertOk()->assertJsonCount(2000, 'metrics');
        $this->assertEquals(now()->subSeconds(2000), Carbon::parse($response->json('metrics.0.created_at')));
        $this->assertEquals(now()->subSecond(), Carbon::parse($response->json('metrics.1999.created_at')));
    }

    public function test_history_access_is_limited_to_authorized_users(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $server = $owner->servers()->create(['name' => 'Test', 'ip_address' => '127.0.0.1', 'agent_token' => Str::random(40)]);
        $this->getJson(route('servers.metrics', $server))->assertUnauthorized();
        $this->actingAs($viewer)->getJson(route('servers.metrics', $server))->assertForbidden();
        $server->assignedUsers()->attach($viewer);
        $this->actingAs($viewer)->getJson(route('servers.metrics', $server))->assertOk();
    }
}
