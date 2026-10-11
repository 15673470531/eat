<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['game_account.appid' => 'game-app', 'game_account.secret' => 'test-secret']);
    }

    private function payload(): array
    {
        return ['v' => 1, 'gold' => 0, 'mastery' => [], 'equip' => ['weapon' => null, 'armor' => null, 'trinket' => null], 'bag' => [], 'best' => ['wave' => 1, 'kills' => 0], 'runs' => 0, 'chapterCompleted' => 0, 'storySeen' => false];
    }

    private function login(string $id = 'player-a'): string
    {
        Http::swap(new Factory);
        Http::fake(['api.weixin.qq.com/*' => Http::response(['openid' => $id, 'session_key' => 'must-not-leak'])]);
        $r = $this->postJson('/api/game-account/login', ['code' => 'wx-code'])->assertOk();
        $this->assertStringNotContainsString('must-not-leak', $r->getContent());
        $this->assertStringNotContainsString($id, $r->getContent());

        return $r->json('data.token');
    }

    public function test_login_isolated_from_old_users_and_tokens(): void
    {
        $token = $this->login();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->withToken($token)->getJson('/api/game-account/me')->assertOk()->assertJsonPath('data.tutorial_completed', false);
        $this->withToken($token)->getJson('/api/user/info')->assertUnauthorized();
        $this->withToken($token)->putJson('/api/game-account/save', ['revision' => 1, 'request_id' => (string) Str::uuid(), 'payload' => $this->payload()])->assertForbidden();
        $this->withToken('invalid')->getJson('/api/game-account/me')->assertUnauthorized();
    }

    public function test_onboarding_is_once_and_reward_is_server_defined(): void
    {
        $token = $this->login();
        $this->withToken($token);
        $p = $this->payload();
        $p['gold'] = 999;
        $p['mastery'] = ['sword' => 900];
        $r = $this->postJson('/api/game-account/onboarding', ['source' => 'tutorial', 'payload' => $p])->assertOk()->assertJsonPath('data.payload.gold', 0)->assertJsonPath('data.payload.equip.armor.id', 'prologue-armor');
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $p])->assertOk()->assertJsonPath('data.revision', 1)->assertJsonPath('data.payload.gold', 0);
        $this->assertDatabaseCount('game_player_saves', 1);
    }

    public function test_save_revision_retry_conflict_and_account_boundaries(): void
    {
        $token = $this->login();
        $this->withToken($token);
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $this->payload()])->assertOk();
        $p = $this->payload();
        $p['mastery'] = ['sword' => 150];
        $body = ['revision' => 1, 'request_id' => (string) Str::uuid(), 'payload' => $p];
        $this->putJson('/api/game-account/save', $body)->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson('/api/game-account/save', $body)->assertOk()->assertJsonPath('data.revision', 2);
        $body['payload']['gold'] = 20;
        $this->putJson('/api/game-account/save', $body)->assertConflict();
        $body['request_id'] = (string) Str::uuid();
        $this->putJson('/api/game-account/save', $body)->assertConflict()->assertJsonPath('data.payload.mastery.sword', 150);
        $this->assertDatabaseCount('game_save_backups', 1);
        $other = $this->login('player-b');
        $this->withToken($other)->getJson('/api/game-account/me')->assertOk()->assertJsonPath('data.save', null);
        $this->assertDatabaseCount('game_players', 2);
    }

    public function test_invalid_wx_code_missing_config_and_expired_session(): void
    {
        config(['game_account.secret' => '']);
        $this->postJson('/api/game-account/login', ['code' => 'x'])->assertStatus(503);
        config(['game_account.secret' => 'test-secret']);
        Http::fake(['*' => Http::response(['errcode' => 40029])]);
        $this->postJson('/api/game-account/login', ['code' => 'x'])->assertUnprocessable();
        $this->assertDatabaseCount('game_players', 0);
        $token = $this->login();
        DB::table('game_player_sessions')->update(['expires_at' => now()->subMinute()]);
        $this->withToken($token)->getJson('/api/game-account/me')->assertUnauthorized();
    }

    public function test_payload_rejects_transient_or_malformed_fields(): void
    {
        $this->withToken($this->login());
        $p = $this->payload();
        $p['run'] = ['hp' => 100];
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $p])->assertUnprocessable();
        $p = $this->payload();
        $p['mastery'] = ['sword' => -1];
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $p])->assertUnprocessable();
        $p = $this->payload();
        $p['equip']['weapon'] = ['name' => 'broken'];
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $p])->assertUnprocessable();
    }

    public function test_login_diagnostics_do_not_expose_credentials(): void
    {
        Http::swap(new Factory);
        Http::fake(['api.weixin.qq.com/*' => Http::response(['errcode' => 40029, 'errmsg' => 'private-detail'])]);
        $r = $this->postJson('/api/game-account/login', ['code' => 'bad-code', 'client_appid' => 'game-app'])->assertStatus(422);
        $this->assertStringContainsString('40029', $r->json('message'));
        $this->assertStringNotContainsString('private-detail', $r->getContent());
        $this->assertDatabaseCount('game_players', 0);
        Http::swap(new Factory);
        Http::fake();
        $this->postJson('/api/game-account/login', ['code' => 'code', 'client_appid' => 'other-app'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_current_weapons_counts_and_mods_round_trip_with_revision_conflict(): void
    {
        $this->withToken($this->login());
        $p = $this->payload();
        $p['v'] = 2;
        $p['weaponCount'] = ['sword' => 1, 'boomerang' => 3, 'knife' => 2];
        $p['loadout'] = ['weapon' => 'sword', 'mods' => []];
        $this->postJson('/api/game-account/onboarding', ['source' => 'tutorial', 'payload' => $p])
            ->assertOk()->assertJsonPath('data.payload.loadout.weapon', 'sword');
        $item = ['id' => 'knife-1', 'slot' => 'weapon', 'slotName' => '武器', 'kind' => 'knife',
            'name' => '飞刀', 'rarity' => 1, 'rarityName' => '普通', 'color' => '#ffffff', 'affixes' => [], 'score' => 0];
        $p['bag'] = [$item, array_merge($item, ['id' => 'boom-1', 'kind' => 'boomerang', 'name' => '回旋镖'])];
        $p['equip']['weapon'] = $item;
        $p['mastery'] = ['sword' => 600, 'knife' => 150, 'boomerang' => 450];
        $p['loadout'] = ['weapon' => 'knife', 'mods' => ['sword' => 'swift']];
        $body = ['revision' => 1, 'request_id' => (string) Str::uuid(), 'payload' => $p];
        $this->putJson('/api/game-account/save', $body)->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson('/api/game-account/save', $body)->assertOk()->assertJsonPath('data.revision', 2);
        $this->getJson('/api/game-account/me')->assertOk()
            ->assertJsonPath('data.save.payload.weaponCount.boomerang', 3)
            ->assertJsonPath('data.save.payload.mastery.knife', 150)
            ->assertJsonPath('data.save.payload.loadout.mods.sword', 'swift')
            ->assertJsonPath('data.save.payload.equip.weapon.kind', 'knife');
        $body['request_id'] = (string) Str::uuid();
        $this->putJson('/api/game-account/save', $body)->assertConflict()
            ->assertJsonPath('data.payload.weaponCount.knife', 2);
    }

    public function test_loadout_rejects_unowned_weapon_locked_mod_and_unknown_keys(): void
    {
        $this->withToken($this->login());
        $p = $this->payload();
        foreach ([[], ['weapon' => 'knife', 'mods' => []], ['weapon' => 'sword', 'mods' => ['sword' => 'giant']],
            ['weapon' => 'sword', 'mods' => ['sword' => 'unknown']], ['weapon' => 'sword', 'mods' => ['knife' => 'giant']]] as $loadout) {
            $p['loadout'] = $loadout;
            $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $p])->assertUnprocessable();
        }
        $p['loadout'] = ['weapon' => 'sword', 'mods' => []];
        $p['weaponCount'] = ['knife' => -1];
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $p])->assertUnprocessable();
    }

    public function test_v1_legacy_weapons_are_still_accepted(): void
    {
        $this->withToken($this->login());
        $p = $this->payload();
        $p['mastery'] = ['dagger' => 600, 'sword' => 650];
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $p])->assertOk()
            ->assertJsonPath('data.payload.mastery.sword', 650);
    }

    public function test_real_client_snapshot_passes_save_and_restore(): void
    {
        $this->withToken($this->login());
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/game-current-save.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->postJson('/api/game-account/onboarding', ['source' => 'legacy', 'payload' => $payload])->assertOk();
        $this->putJson('/api/game-account/save', ['revision' => 1, 'request_id' => (string) Str::uuid(), 'payload' => $payload])->assertOk();
        $restored = $this->getJson('/api/game-account/me')->assertOk()->json('data.save.payload');
        $this->assertEquals($payload, $restored);
    }
}
