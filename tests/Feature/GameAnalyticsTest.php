<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['game_id' => 'kdtl', 'player_id' => (string) Str::uuid(), 'events' => [[
            'run_id' => 'run-123', 'seq' => 1, 'event_name' => 'kdtl_run_start', 'build' => 'test-v1',
            'stage' => 1, 'wave' => 1, 'weapon' => 'sword', 'seconds' => 0, 'detail' => 'new',
            'test_device' => false, 'previous_run_id' => '',
            'occurred_at' => CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ]]];
    }

    private function admin(bool $enabled = true): User
    {
        $user = User::factory()->create(['is_admin' => $enabled]);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_batch_retries_are_idempotent_and_do_not_create_business_users(): void
    {
        $payload = $this->payload();
        $payload['events'][] = array_replace($payload['events'][0], ['seq' => 2, 'event_name' => 'kdtl_wave_complete']);
        $this->postJson('/api/game-analytics/events', $payload)->assertOk()->assertJsonCount(2, 'data.acknowledged');
        $payload['events'][0]['detail'] = 'changed';
        $this->postJson('/api/game-analytics/events', $payload)->assertOk();
        $this->assertDatabaseCount('game_analytics_events', 2);
        $this->assertDatabaseHas('game_analytics_events', ['seq' => 1, 'detail' => 'new']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_kitchens', 0);
    }

    public function test_invalid_batch_is_atomic_and_limits_are_enforced(): void
    {
        $payload = $this->payload();
        $payload['events'][] = array_replace($payload['events'][0], ['seq' => 2, 'event_name' => 'invented']);
        $this->postJson('/api/game-analytics/events', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('game_analytics_events', 0);
        $payload['events'] = array_fill(0, 51, $this->payload()['events'][0]);
        $this->postJson('/api/game-analytics/events', $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['events'][0]['nickname'] = 'not collected';
        $this->postJson('/api/game-analytics/events', $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['events'][0]['occurred_at'] = '2000-01-01T00:00:00Z';
        $this->postJson('/api/game-analytics/events', $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['game_id'] = 'eatwhat';
        $this->postJson('/api/game-analytics/events', $payload)->assertUnprocessable();
        $this->post('/api/game-analytics/events', [])->assertStatus(415);
        $this->postJson('/api/game-analytics/events', ['padding' => str_repeat('a', 65537)])->assertStatus(413);
    }

    public function test_reports_require_admin_and_exclude_test_records_by_default(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/game-analytics/events', $payload)->assertOk();
        $payload['events'][0]['seq'] = 2;
        $payload['events'][0]['test_device'] = true;
        $this->postJson('/api/game-analytics/events', $payload)->assertOk();
        $this->getJson('/api/game-analytics/summary')->assertUnauthorized();
        $this->getJson('/api/game-analytics/events')->assertUnauthorized();
        $user = $this->admin(false);
        $this->getJson('/api/game-analytics/summary')->assertForbidden();
        $this->getJson('/api/game-analytics/events')->assertForbidden();
        $user->update(['is_admin' => true]);
        $this->getJson('/api/game-analytics/summary')->assertOk()->assertJsonPath('data.events_total', 1)->assertJsonPath('data.runs', 1);
        $this->getJson('/api/game-analytics/summary?include_test=1')->assertOk()->assertJsonPath('data.events_total', 2);
        $this->getJson('/api/game-analytics/events?per_page=1')->assertOk()->assertJsonCount(1, 'data.items.data');
        $this->getJson('/api/game-analytics/events?per_page=101')->assertUnprocessable();
        $this->getJson('/api/game-analytics/summary?from=2026-01-01&to=2026-03-01')->assertUnprocessable();
        $this->getJson('/api/game-analytics/summary?from=2026-10-07&to=2026-10-01')->assertUnprocessable();
        $this->assertNull($user->fresh()->last_active_at);
    }

    public function test_shanghai_date_boundaries_and_build_filters(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T05:00:00Z'));
        $p = $this->payload();
        $p['events'][0]['occurred_at'] = '2026-10-06T16:00:00Z';
        $this->postJson('/api/game-analytics/events', $p)->assertOk();
        $p['events'][0]['seq'] = 2;
        $p['events'][0]['occurred_at'] = '2026-10-06T15:59:59Z';
        $this->postJson('/api/game-analytics/events', $p)->assertOk();
        $this->admin();
        $this->getJson('/api/game-analytics/summary?from=2026-10-07&to=2026-10-07')->assertOk()->assertJsonPath('data.events_total', 1);
        $this->getJson('/api/game-analytics/summary?build=other')->assertOk()->assertJsonPath('data.events_total', 0);
    }

    public function test_disabled_ingest_and_rate_limit(): void
    {
        config(['game_analytics.enabled' => false]);
        $this->postJson('/api/game-analytics/events', $this->payload())->assertStatus(503);
        config(['game_analytics.enabled' => true]);
        for ($i = 0; $i < 59; $i++) {
            $this->postJson('/api/game-analytics/events', $this->payload())->assertOk();
        }
        $this->postJson('/api/game-analytics/events', $this->payload())->assertStatus(429);
    }

    public function test_ingest_does_not_exhaust_existing_business_rate_limits(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/game-analytics/events', $this->payload())->assertOk();
        }
        $this->postJson('/api/game-analytics/events', $this->payload())->assertStatus(429);
        $this->postJson('/api/user/login', [])->assertUnprocessable();
        $this->admin();
        $this->getJson('/api/game-analytics/summary')->assertOk();
    }

    public function test_migration_rollback_only_removes_analytics_table(): void
    {
        $migration = require database_path('migrations/2026_10_07_000001_create_game_analytics_events_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('game_analytics_events'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('recipes'));
        $migration->up();
    }
}
