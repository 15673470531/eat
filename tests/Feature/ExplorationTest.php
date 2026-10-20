<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
class ExplorationTest extends TestCase {
 use RefreshDatabase;
 private function sample(){ $e=json_decode(file_get_contents(base_path('tests/exploration-sample.json')),true);$e['events'][0]['occurred_at']=now('UTC')->format('Y-m-d\TH:i:s\Z');$e['events'][0]['record']['startedAt']=$e['events'][0]['occurred_at'];return $e; }
 public function test_guest_ingest_idempotency_order_terminal_and_report(){
  $p=$this->sample();$this->postJson('/api/game-analytics/exploration',$p)->assertOk()->assertJsonPath('data.acknowledged.0.seq',1);$this->postJson('/api/game-analytics/exploration',$p)->assertOk();$this->assertDatabaseCount('game_exploration_events',1);
  $p['events'][0]['seq']=3;$p['events'][0]['record']['result']='extract';$p['events'][0]['event']='finish';$p['events'][0]['record']['materials']=['bankedGold'=>10,'bankedFragments'=>['green'=>8]];$this->postJson('/api/game-analytics/exploration',$p)->assertOk();
  $p['events'][0]['seq']=2;$p['events'][0]['record']['result']='active';$this->postJson('/api/game-analytics/exploration',$p)->assertOk();$this->assertDatabaseHas('game_exploration_runs',['result'=>'extract','last_seq'=>3]);$p['events'][0]['seq']=4;$p['events'][0]['record']['materials']=[];$this->postJson('/api/game-analytics/exploration',$p)->assertOk();$this->assertSame(10,json_decode(\Illuminate\Support\Facades\DB::table('game_exploration_runs')->first()->record,true)['materials']['bankedGold']);
  $this->get('/admin/exploration?include_test=1')->assertOk()->assertSee('成功撤离')->assertSee('房间表现');
 }
 public function test_validation_and_test_filter(){
  $p=$this->sample();$p['events'][0]['test_device']=true;$this->postJson('/api/game-analytics/exploration',$p)->assertOk();$this->get('/admin/exploration')->assertOk()->assertDontSee($p['events'][0]['run_id']);$this->get('/admin/exploration?include_test=1&run=1')->assertOk()->assertSee($p['events'][0]['run_id']);
  $p['events'][0]['record']['rooms']['bogus']=['visits'=>1,'seconds'=>1,'kills'=>1,'damage'=>0,'cleared'=>true];$this->postJson('/api/game-analytics/exploration',$p)->assertUnprocessable();
  $p=$this->sample();$p['events'][0]['record']['route']=[['scene'=>['bad'],'at'=>1]];$this->postJson('/api/game-analytics/exploration',$p)->assertUnprocessable();
 }
 public function test_beijing_display_and_midnight_filter_keep_utc_storage(){
  $p=$this->sample();$e=$p['events'][0];$record=$e['record'];
  $id=\Illuminate\Support\Facades\DB::table('game_exploration_runs')->insertGetId(['player_id'=>$e['player_id'],'run_id'=>'timezone-check','build'=>'tz-test','test_device'=>false,'last_seq'=>1,'result'=>'active','sword_level'=>1,'started_at'=>'2026-10-19 16:05:00','occurred_at'=>'2026-10-19 16:06:00','record'=>json_encode($record)]);
  \Illuminate\Support\Facades\DB::table('game_exploration_events')->insert(['player_id'=>$e['player_id'],'run_id'=>'timezone-check','seq'=>1,'event'=>'start','occurred_at'=>'2026-10-19 16:06:00','data'=>'{}']);
  $this->get('/admin/exploration?from=2026-10-20&to=2026-10-20&run='.$id)->assertOk()->assertSee('2026-10-20 00:05:00')->assertSee('2026-10-20 00:06:00')->assertDontSee('2026-10-19 16:05:00');
  $this->get('/admin/exploration?from=2026-10-19&to=2026-10-19')->assertOk()->assertDontSee('timezone-check');
  $this->assertDatabaseHas('game_exploration_runs',['id'=>$id,'started_at'=>'2026-10-19 16:05:00']);
 }
}
