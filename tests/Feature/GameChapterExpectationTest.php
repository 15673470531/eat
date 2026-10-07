<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameChapterExpectationTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $suffix): string
    {
        config(['game_account.appid' => 'expect-test']);
        $id = DB::table('game_players')->insertGetId(['appid' => 'expect-test', 'openid' => $suffix]);
        $token = 'gp_'.hash('sha256', $suffix);
        DB::table('game_player_sessions')->insert(['player_id' => $id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

        return $token;
    }

    public function test_expectations_require_login_and_are_account_scoped_and_idempotent(): void
    {
        $url = '/api/game-account/chapters/2/expectation';
        $this->getJson($url)->assertUnauthorized();
        $this->putJson($url)->assertUnauthorized();
        $first = $this->player('first');
        $this->withToken($first)->getJson($url)->assertOk()->assertJsonPath('data.expected', false);
        $this->putJson($url, ['player_id' => 999, 'chapter' => 99])->assertOk()->assertJsonPath('data.expected', true);
        $created = DB::table('game_chapter_expectations')->value('created_at');
        $this->travel(5)->minutes();
        $this->putJson($url)->assertOk();
        $this->assertDatabaseCount('game_chapter_expectations', 1);
        $this->assertEquals($created, DB::table('game_chapter_expectations')->value('created_at'));
        $second = $this->player('second');
        $this->withToken($second)->getJson($url)->assertOk()->assertJsonPath('data.expected', false);
        $this->putJson($url)->assertOk();
        $this->assertDatabaseCount('game_chapter_expectations', 2);
        $this->withToken($first)->getJson($url)->assertOk()->assertJsonPath('data.expected', true);
        $this->putJson('/api/game-account/chapters/3/expectation')->assertNotFound();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('game_player_saves', 0);
    }
}
