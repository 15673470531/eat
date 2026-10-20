<?php
namespace App\Http\Controllers\Api\GameAnalytics;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;
class ExplorationController extends Controller {
 public function store(Request $request) {
  $data=$request->validate([
   'events'=>'required|array|min:1|max:1',
   'events.*'=>'required|array:player_id,run_id,seq,event,occurred_at,build,test_device,data,record',
   'events.*.player_id'=>'required|uuid', 'events.*.run_id'=>['required','string','max:64','regex:/^[a-z0-9-]+$/'],
   'events.*.seq'=>'required|integer|between:1,10000000','events.*.build'=>'required|string|max:64','events.*.test_device'=>'required|boolean',
   'events.*.event'=>['required',Rule::in(['gate_clear','start','resume','room_enter','room_clear','dialogue','checkpoint','background','suspend','boss_kill','card_offer','card_select','card_refresh','chief_wall','bell_interrupt','bell_attempt','rescue','bell_break','retreat_hint','pickup','finish'])],
   'events.*.occurred_at'=>['required','date_format:Y-m-d\TH:i:s\Z','after_or_equal:'.now('UTC')->subDays(8)->format('Y-m-d\TH:i:s\Z'),'before_or_equal:'.now('UTC')->addMinutes(10)->format('Y-m-d\TH:i:s\Z')],
   'events.*.data'=>'present|array', 'events.*.record'=>'required|array',
   'events.*.record.startedAt'=>['required','date_format:Y-m-d\TH:i:s\Z','before_or_equal:'.now('UTC')->addMinutes(10)->format('Y-m-d\TH:i:s\Z')],
   'events.*.record.result'=>['required',Rule::in(['active','clear','extract','death','abandon'])],
   'events.*.record.startLevels.sword'=>'required|integer|between:1,5',
   'events.*.record.rooms'=>'present|array|max:24','events.*.record.route'=>'present|array|max:60','events.*.record.choices'=>'present|array|max:60',
   'events.*.record.rooms.*.seconds'=>'required|numeric|between:0,604800','events.*.record.rooms.*.damage'=>'required|numeric|between:0,10000000',
   'events.*.record.rooms.*.kills'=>'required|integer|between:0,1000000','events.*.record.rooms.*.visits'=>'required|integer|between:0,100000','events.*.record.rooms.*.cleared'=>'required|boolean',
   'events.*.record.seconds'=>'required|numeric|between:0,604800',
   'events.*.record.choices.*.at'=>'required|numeric|between:0,604800','events.*.record.choices.*.scene'=>'required|string|max:32',
   'events.*.record.choices.*.selected.weapon'=>'required|in:sword,knife','events.*.record.choices.*.selected.quality'=>'required|in:green,blue,purple,gold','events.*.record.choices.*.selected.effect'=>'required|string|max:100',
   'events.*.record.materials.gold'=>'sometimes|integer|between:0,1000000000','events.*.record.materials.bankedGold'=>'sometimes|integer|between:0,1000000000',
   'events.*.record.materials.bankedFragments.*'=>'integer|between:0,1000000000','events.*.record.lost.gold'=>'sometimes|integer|between:0,1000000000','events.*.record.lost.fragments.*'=>'integer|between:0,1000000000',
   'events.*.record.scene'=>'required|string|max:32','events.*.record.deathSource'=>'sometimes|string|max:80',
   'events.*.record.route.*.scene'=>'required|string|max:32','events.*.record.route.*.at'=>'required|numeric|between:0,604800',
   'events.*.record.wallStuns'=>'required|integer|between:0,100000','events.*.record.bellAttempts'=>'required|integer|between:0,100000','events.*.record.bellInterrupts'=>'required|integer|between:0,100000',
   'events.*.record.askedBoss'=>'required|boolean','events.*.record.bellBroken'=>'required|boolean','events.*.record.partial'=>'required|boolean','events.*.record.dropped'=>'required|integer|between:0,10000000',
  ]);
  $ack=DB::transaction(function()use($data){$ack=[];foreach($data['events'] as $e){$r=$e['record'];if(strlen(json_encode($r))>48000||strlen(json_encode($e['data']))>4000||$r['startedAt']>$e['occurred_at'])throw ValidationException::withMessages(['events.0.record'=>'记录过大或时间无效']);
   foreach(array_keys($r['rooms']) as $key)if(!in_array($key,['outdoor','home','weapon','weaponInner','weaponDeep','rescue','rescueHall','rescueInner','charm','hallGroup','hallLeader','hallClerkGuard','hallClerk','hallMemberGuard','hallMember','hallDeputyGuard','hallDeputy','boss']))throw ValidationException::withMessages(['events.0.record.rooms'=>'未知房间']);
   $key=['player_id'=>$e['player_id'],'run_id'=>$e['run_id']];$at=CarbonImmutable::parse($e['occurred_at'])->utc()->format('Y-m-d H:i:s');
   $values=['build'=>$e['build'],'test_device'=>$e['test_device'],'last_seq'=>$e['seq'],'result'=>$r['result'],'sword_level'=>$r['startLevels']['sword'],'started_at'=>CarbonImmutable::parse($r['startedAt'])->utc()->format('Y-m-d H:i:s'),'occurred_at'=>$at,'record'=>json_encode($r)];
   DB::table('game_exploration_runs')->insertOrIgnore($key+$values);$run=DB::table('game_exploration_runs')->where($key)->lockForUpdate()->first();
   if($e['seq']>$run->last_seq){if($run->result!=='active'&&$values['result']==='active'){ $values['result']=$run->result;$values['record']=$run->record; }DB::table('game_exploration_runs')->where('id',$run->id)->update($values);}
   DB::table('game_exploration_events')->insertOrIgnore($key+['seq'=>$e['seq'],'event'=>$e['event'],'occurred_at'=>$at,'data'=>json_encode($e['data'])]);$ack[]=['run_id'=>$e['run_id'],'seq'=>$e['seq']];
  }return $ack;});return response()->json(['data'=>['acknowledged'=>$ack]]);
 }
}
