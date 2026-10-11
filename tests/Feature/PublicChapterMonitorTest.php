<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicChapterMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_report_without_login_or_account_creation(): void
    {
        foreach (['/admin', '/admin/chapter-monitor'] as $url) {
            $this->get($url)->assertOk()->assertSee('第一章监控')->assertSee('开局武器对照')
                ->assertDontSee('type="password"', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        $this->get('/admin/login')->assertRedirect('/admin');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get('/admin/manage/users')->assertRedirect('/admin/manage/login');
        $this->getJson('/api/game-analytics/chapter-monitor/summary')->assertUnauthorized();
    }

    private function seedRun(): int
    {
        $state = ['weapon' => 'knife', 'mod' => 'pierce', 'awakened' => false, 'hits' => 3, 'elite_kills' => 2, 'boss_kills' => 1, 'completed_waves' => [1, 2, 3], 'cards' => ['knife_range' => 2], 'dropped_events' => 0, 'wave' => 3, 'active_seconds' => 420];
        $id = DB::table('game_chapter_runs')->insertGetId(['player_id' => 17, 'run_id' => 'public-preview', 'chapter' => 1, 'build' => 'sample-v1', 'test_device' => false, 'has_start' => true, 'start_weapon' => 'knife', 'weapon' => 'knife', 'status' => 'completed', 'last_seq' => 1, 'event_count' => 1, 'max_wave' => 3, 'active_seconds' => 420, 'revives' => 1, 'kills' => 90, 'started_at' => now('UTC'), 'last_occurred_at' => now('UTC'), 'received_at' => now('UTC'), 'state' => json_encode($state)]);
        DB::table('game_chapter_events')->insert(['player_id' => 17, 'run_id' => 'public-preview', 'seq' => 1, 'event' => 'complete', 'detail' => '', 'build' => 'sample-v1', 'test_device' => false, 'occurred_at' => now('UTC'), 'received_at' => now('UTC'), 'state' => json_encode($state)]);

        return $id;
    }

    public function test_guest_filter_and_timeline_links_use_normal_http_navigation(): void
    {
        $id = $this->seedRun();
        $response = $this->get('/admin?build=sample-v1&player_id=17&run_id='.$id);
        $response->assertOk()->assertSee('public-preview')->assertSee('刀境·远')->assertSee('远锋')->assertSee('已完成波次：1、2、3')->assertSee('method="get"', false)->assertSee('name="player_id"', false);
        preg_match('/href="([^"]+)">查看过程<\/a>/', $response->getContent(), $match);
        $this->assertNotEmpty($match);
        parse_str(parse_url(html_entity_decode($match[1]), PHP_URL_QUERY), $query);
        $this->assertEquals(['build'=>'sample-v1','player_id'=>17,'run_id'=>$id,'event_page'=>1], $query);
        $this->get('/admin?build=not-this-version')->assertOk()->assertDontSee('public-preview');
        $this->get('/admin?run_id=9999')->assertNotFound();
        $this->get('/admin?event_page=0')->assertStatus(422);
        $this->get('/admin?from=2026-01-01&to=2026-04-01')->assertStatus(422)->assertSee('重新筛选');
        if ($path = getenv('MONITOR_PREVIEW_HTML')) {
            file_put_contents($path, $response->getContent());
        }
    }
}
