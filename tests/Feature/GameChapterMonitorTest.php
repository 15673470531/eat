<?php

namespace Tests\Feature;

use App\Filament\Pages\ChapterMonitor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GameChapterMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $name = 'one'): int
    {
        config(['game_account.appid' => 'game-app', 'game_account.secret' => 'test']);
        Http::swap(new Factory);
        Http::fake(['api.weixin.qq.com/*' => Http::response(['openid' => $name, 'session_key' => 'secret'])]);
        $r = $this->postJson('/api/game-account/login', ['code' => 'code'])->assertOk();
        $this->withToken($r->json('data.token'));

        return DB::table('game_players')->where('openid', $name)->value('id');
    }

    private function event(string $id = 'one', int $seq = 1, string $event = 'start', array $state = []): array
    {
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return ['run_id' => $id, 'seq' => $seq, 'chapter' => 1, 'event' => $event, 'detail' => '', 'build' => 'monitor-v1', 'test_device' => false, 'occurred_at' => $now,
            'state' => array_replace(['status' => 'active', 'started_at' => $now, 'complete_start' => true, 'start_weapon' => 'knife', 'stage' => 1, 'wave' => 1, 'completed_waves' => [],
                'weapon' => 'knife', 'mod' => 'pierce', 'cards' => [], 'awakened' => false, 'active_seconds' => 0, 'kills' => 0, 'hits' => 0, 'revives' => 0, 'elite_kills' => 0, 'boss_kills' => 0, 'hp' => 100, 'dropped_events' => 0], $state)];
    }

    private function ingest(array $events)
    {
        return $this->postJson('/api/game-account/chapter-events', ['events' => $events]);
    }

    private function admin(bool $enabled = true): User
    {
        $u = User::factory()->create(['is_admin' => $enabled]);
        $this->actingAs($u, 'sanctum');

        return $u;
    }

    public function test_authentication_validation_and_account_isolation(): void
    {
        $this->ingest([$this->event()])->assertUnauthorized();
        $a = $this->login();
        $this->ingest([$this->event()])->assertOk();
        $b = $this->login('two');
        $this->ingest([$this->event()])->assertOk();
        $this->assertDatabaseHas('game_chapter_runs', ['player_id' => $a, 'run_id' => 'one']);
        $this->assertDatabaseHas('game_chapter_runs', ['player_id' => $b, 'run_id' => 'one']);
        $bad = $this->event('bad');
        $bad['player_id'] = $a;
        $this->ingest([$this->event('valid'), $bad])->assertUnprocessable();
        $this->assertDatabaseCount('game_chapter_runs', 2);
        $bad = $this->event('bad');
        $bad['chapter'] = 2;
        $this->ingest([$bad])->assertUnprocessable();
        $bad = $this->event('bad');
        $bad['state']['cards'] = ['<script>' => 1];
        $this->ingest([$bad])->assertUnprocessable();
        $this->getJson('/api/game-account/me')->assertOk();
        $this->assertDatabaseCount('game_player_logins', 3);
        $this->assertDatabaseHas('game_player_logins', ['player_id' => $b, 'source' => 'restore']);
    }

    public function test_offline_duplicates_and_out_of_order_events_preserve_latest_snapshot(): void
    {
        $this->login();
        $end = $this->event('one', 5, 'complete', ['status' => 'completed', 'wave' => 3, 'completed_waves' => [1, 2, 3], 'active_seconds' => 400, 'kills' => 60, 'weapon' => 'sword']);
        $this->ingest([$end])->assertOk();
        $this->assertDatabaseHas('game_chapter_runs', ['has_start' => false, 'status' => 'completed']);
        $start = $this->event();
        $this->ingest([$start, $end])->assertOk()->assertJsonCount(2, 'data.acknowledged');
        $start['detail'] = 'duplicate changed';
        $this->ingest([$start])->assertOk();
        $this->assertDatabaseCount('game_chapter_events', 2);
        $this->assertDatabaseHas('game_chapter_events', ['seq' => 1, 'detail' => '']);
        $this->assertDatabaseHas('game_chapter_runs', ['event_count' => 2, 'has_start' => true, 'last_seq' => 5, 'status' => 'completed', 'weapon' => 'sword', 'start_weapon' => 'knife', 'active_seconds' => 400]);
        $late = $this->event('one', 4, 'heartbeat');
        $late['test_device'] = true;
        $this->ingest([$late])->assertOk();
        $this->assertDatabaseHas('game_chapter_runs', ['test_device' => true, 'last_seq' => 5, 'status' => 'completed']);
    }

    public function test_death_revive_and_complete_are_one_attempt(): void
    {
        $this->login();
        $this->ingest([$this->event(), $this->event('one', 2, 'death', ['status' => 'dead']), $this->event('one', 3, 'revive', ['revives' => 1]), $this->event('one', 4, 'complete', ['status' => 'completed', 'wave' => 3, 'revives' => 1, 'active_seconds' => 500])])->assertOk();
        $this->assertDatabaseCount('game_chapter_runs', 1);
        $this->assertDatabaseHas('game_chapter_runs', ['event_count' => 4, 'status' => 'completed', 'revives' => 1]);
    }

    public function test_reports_permissions_cohorts_test_filter_and_detail(): void
    {
        $id = $this->login();
        $this->ingest([$this->event(), $this->event('one', 2, 'complete', ['status' => 'completed', 'active_seconds' => 500])])->assertOk();
        $test = $this->event('test');
        $test['test_device'] = true;
        $this->ingest([$test, $this->event('second'), $this->event('partial', 1, 'resume', ['complete_start' => false])])->assertOk();
        $url = '/api/game-analytics/chapter-monitor/';
        $this->getJson($url.'summary')->assertUnauthorized();
        $u = $this->admin(false);
        $this->getJson($url.'summary')->assertForbidden();
        $u->update(['is_admin' => true]);
        $this->getJson($url.'summary')->assertOk()->assertJsonPath('data.runs', 2)->assertJsonPath('data.completed', 1)->assertJsonPath('data.completion_rate', 50)->assertJsonPath('data.partial_runs', 1)->assertJsonPath('data.first_attempts', 1)->assertJsonPath('data.first_attempt_completed', 1)->assertJsonPath('data.login_players_started', 1);
        $this->getJson($url.'summary?include_test=1')->assertOk()->assertJsonPath('data.runs', 3);
        $this->getJson($url.'summary?build=missing')->assertOk()->assertJsonPath('data.runs', 0);
        $this->getJson($url.'summary?from=2026-01-01&to=2026-04-01')->assertUnprocessable();
        $this->getJson($url.'runs')->assertOk()->assertJsonCount(3, 'data.data');
        $this->getJson($url.'players/'.$id)->assertOk()->assertJsonPath('data.profile.id', $id)->assertJsonMissingPath('data.profile.openid');
        $run = DB::table('game_chapter_runs')->where('run_id', 'one')->value('id');
        $this->getJson($url.'runs/'.$run)->assertOk()->assertJsonCount(2, 'data.events.data');
        $this->getJson($url.'runs/999999')->assertNotFound();
    }

    public function test_shanghai_boundaries_and_first_attempt_is_not_reset_by_date_filter(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-11T05:00:00Z'));
        $this->login();
        $old = $this->event('old');
        $old['occurred_at'] = $old['state']['started_at'] = '2026-10-10T15:59:59Z';
        $new = $this->event('new');
        $new['occurred_at'] = $new['state']['started_at'] = '2026-10-10T16:00:00Z';
        $this->ingest([$old, $new])->assertOk();
        $this->admin();
        $this->getJson('/api/game-analytics/chapter-monitor/summary?from=2026-10-11&to=2026-10-11')->assertOk()->assertJsonPath('data.runs', 1)->assertJsonPath('data.first_attempts', 0);
        DB::table('game_chapter_runs')->where('run_id', 'old')->update(['last_occurred_at' => now('UTC')->subDays(2)]);
        $this->getJson('/api/game-analytics/chapter-monitor/summary')->assertOk()->assertJsonPath('data.interrupted_unknown', 1);
    }

    public function test_monitor_rate_limit_does_not_block_cloud_saves(): void
    {
        $this->login();
        for ($i = 0; $i < 60; $i++) {
            $this->ingest([$this->event()])->assertOk();
        }
        $this->ingest([$this->event()])->assertStatus(429);
        $this->getJson('/api/game-account/me')->assertOk();
    }

    public function test_filament_page_user_drilldown_and_invalid_filters(): void
    {
        $id = $this->login();
        $this->ingest([$this->event(), $this->event('one', 2, 'card_select', ['cards' => ['knife_range' => 2]])])->assertOk();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $run = DB::table('game_chapter_runs')->value('id');
        Livewire::test(ChapterMonitor::class)->assertSee('开局武器对照')->call('user', $id)->assertSee('最近50次登录')->call('openRun', $run)->assertSee('刀境·远')->assertSee('远锋')->set('from', '2026-01-01')->set('to', '2026-04-01')->call('search')->assertHasErrors('to');
        $this->get('/admin/manage/chapter-monitor')->assertOk()->assertSee('第一章监控');
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $normal = User::factory()->create(['is_admin' => false]);
        $this->actingAs($normal);
        $this->assertFalse(ChapterMonitor::canAccess());
        $this->assertFalse($normal->canAccessPanel(Filament::getPanel('admin')));
    }
}
