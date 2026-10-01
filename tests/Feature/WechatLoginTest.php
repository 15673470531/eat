<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
class WechatLoginTest extends TestCase {
 use RefreshDatabase;
 public function test_login_and_logout(): void {
  config(['wechat.appid'=>'test','wechat.secret'=>'secret']);
  Http::fake(['*'=>Http::response(['openid'=>'test_user','session_key'=>'hidden'])]);
  $data=$this->postJson('/api/user/login',['code'=>'valid'])->assertOk()->json('data');
  $this->assertArrayNotHasKey('session_key',$data);
  $this->withToken($data['token'])->getJson('/api/user/info')->assertOk()->assertJsonPath('data.userId',$data['userId']);
  $this->withToken($data['token'])->postJson('/api/user/logout')->assertOk();
  $this->assertDatabaseCount('personal_access_tokens',0);
  $this->postJson('/api/user/login',['code'=>'valid'])->assertOk();
  $this->assertDatabaseCount('users',1);
 }
 public function test_invalid_code_never_creates_fake_user(): void {
  config(['wechat.appid'=>'test','wechat.secret'=>'secret']);
  Http::fake(['*'=>Http::response(['errcode'=>40029])]);
  $this->postJson('/api/user/login',['code'=>'dev_fake'])->assertStatus(422);
  $this->assertDatabaseCount('users',0);
 }
 public function test_missing_config_and_auth(): void {
  config(['wechat.appid'=>null]);
  $this->postJson('/api/user/login',['code'=>'valid'])->assertStatus(503);
  $this->getJson('/api/user/info')->assertStatus(401);
  $this->postJson('/api/user/login',[])->assertStatus(422);
 }
}
