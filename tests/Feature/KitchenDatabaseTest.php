<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\KitchenStore;
class KitchenDatabaseTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->seed(\Database\Seeders\KitchenCatalogSeeder::class);}
 private function user(string $name='first'):User {return User::create(['name'=>$name,'email'=>$name.'@test.invalid','password'=>'test-pass']);}
 private function state():array {return array_merge(KitchenStore::defaults(),['selected'=>['ji_dan','yan'],'inventory'=>[['key'=>'eggs','id'=>'ji_dan','quantity'=>6,'unit'=>'个','location'=>'冷藏'],['key'=>'tofu','id'=>'dou_fu','quantity'=>null,'unit'=>'盒','location'=>'冷藏']],'favorites'=>['dish_qingjiao_chaodan'],'shopping'=>[['id'=>'qing_jiao','checked'=>true,'sources'=>['青椒炒鸡蛋']]]]);}
 public function test_catalog_matches_seed_and_is_repeatable():void {
  $this->seed(\Database\Seeders\KitchenCatalogSeeder::class);
  $data=$this->getJson('/api/catalog')->assertOk()->json('data');
  $this->assertCount(98,$data['ingredients']['items']);$this->assertCount(44,$data['recipes']);
  $expected=json_decode(file_get_contents(database_path('catalog.json')),true);
  $recipes=array_map(function($r){unset($r['revision'],$r['isActive']);return $r;},$data['recipes']);
  $this->assertEquals($expected['recipes'],$recipes);
 }
 public function test_state_roundtrip_idempotency_conflict_and_account_isolation():void {
  $user=$this->user();$this->actingAs($user,'sanctum');
  $this->getJson('/api/kitchen')->assertJsonPath('data.revision',0);
  $body=['revision'=>0,'requestId'=>(string)Str::uuid(),'state'=>$this->state()];
  $this->putJson('/api/kitchen',$body)->assertOk()->assertJsonPath('data.revision',1);
  $this->putJson('/api/kitchen',$body)->assertOk()->assertJsonPath('data.revision',1);
  $this->assertEquals($body['state'],$this->getJson('/api/kitchen')->json('data.state'));
  $stale=$body;$stale['requestId']=(string)Str::uuid();$stale['state']['inventory']=[];
  $this->putJson('/api/kitchen',$stale)->assertStatus(409);
  $this->assertDatabaseCount('user_inventory',2);
  $this->actingAs($this->user('second'),'sanctum');
  $this->getJson('/api/kitchen')->assertJsonPath('data.state.inventory',[])->assertJsonPath('data.state.favorites',[]);
  $this->actingAs($user,'sanctum');
  $body['revision']=1;$body['requestId']=(string)Str::uuid();$body['state']['inventory']=[];$body['state']['shopping']=[];$body['state']['favorites']=[];
  $this->putJson('/api/kitchen',$body)->assertOk();$this->assertDatabaseCount('user_inventory',0);$this->assertDatabaseCount('user_favorites',0);
 }
 public function test_invalid_payload_does_not_partially_write():void {
  $this->actingAs($this->user(),'sanctum');
  $body=['revision'=>0,'requestId'=>(string)Str::uuid(),'state'=>$this->state()];
  $body['state']['inventory'][0]['quantity']=-1;
  $this->putJson('/api/kitchen',$body)->assertStatus(422);$this->assertDatabaseCount('user_inventory',0);
  $body['state']=$this->state();$body['state']['favorites']=['unknown'];
  $this->putJson('/api/kitchen',$body)->assertStatus(422);
 }
 public function test_authentication_required():void {$this->getJson('/api/kitchen')->assertStatus(401);$this->putJson('/api/kitchen',[])->assertStatus(401);}
}
