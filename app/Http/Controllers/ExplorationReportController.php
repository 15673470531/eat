<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
class ExplorationReportController extends Controller {
 public function __invoke(Request $request){
  $f=$request->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from','build'=>'nullable|string|max:64','level'=>'nullable|integer|between:1,5','cohort'=>'nullable|in:first,returning','include_test'=>'nullable|boolean','player'=>'nullable|uuid','run'=>'nullable|integer|min:1','page'=>'nullable|integer|min:1']);
  $from=CarbonImmutable::parse($f['from']??now('Asia/Shanghai')->subDays(6)->toDateString(),'Asia/Shanghai')->startOfDay();$to=CarbonImmutable::parse($f['to']??now('Asia/Shanghai')->toDateString(),'Asia/Shanghai')->endOfDay();abort_if($to->lt($from)||$from->diffInDays($to)>31,422,'请选择不超过31天的范围');
  $q=DB::table('game_exploration_runs')->whereBetween('started_at',[$from->utc()->format('Y-m-d H:i:s'),$to->utc()->format('Y-m-d H:i:s')]);
  if(empty($f['include_test']))$q->where('test_device',false);if(!empty($f['build']))$q->where('build',$f['build']);if(!empty($f['level']))$q->where('sword_level',$f['level']);if(!empty($f['player']))$q->where('player_id',$f['player']);
  if(!empty($f['cohort'])){$method=$f['cohort']==='first'?'whereNotExists':'whereExists';$q->$method(function($e){$e->selectRaw('1')->from('game_exploration_runs as earlier')->whereColumn('earlier.player_id','game_exploration_runs.player_id')->whereColumn('earlier.test_device','game_exploration_runs.test_device')->whereColumn('earlier.id','<','game_exploration_runs.id');});}
  $summary=['runs'=>0,'clear'=>0,'extract'=>0,'death'=>0,'abandon'=>0,'active'=>0,'partial'=>0,'askedBoss'=>0,'wallStuns'=>0,'bellAttempts'=>0,'bellBreak'=>0,'strongClear'=>0];$rooms=[];$weapons=[];
  (clone $q)->orderBy('id')->chunkById(200,function($rows)use(&$summary,&$rooms){foreach($rows as $row){$r=json_decode($row->record,true);if(!empty($r['partial'])){$summary['partial']++;continue;}$summary['runs']++;$summary[$row->result]++;$summary['askedBoss']+=!empty($r['askedBoss'])?1:0;$summary['wallStuns']+=(int)($r['wallStuns']??0);$summary['bellAttempts']+=(int)($r['bellAttempts']??0);$summary['bellBreak']+=!empty($r['bellBroken'])?1:0;$summary['strongClear']+=($row->result==='clear'&&empty($r['bellBroken']))?1:0;foreach($r['rooms']??[] as $name=>$room){$rooms[$name]??=['visits'=>0,'clears'=>0,'seconds'=>0,'damage'=>0,'deaths'=>0];$rooms[$name]['visits']++;$rooms[$name]['clears']+=!empty($room['cleared'])?1:0;$rooms[$name]['seconds']+=(float)$room['seconds'];$rooms[$name]['damage']+=(float)$room['damage'];$rooms[$name]['deaths']+=($row->result==='death'&&($r['scene']??'')===$name)?1:0;}}});
  $runs=(clone $q)->orderByDesc('id')->paginate(30)->withQueryString();$selected=null;$events=[];if(!empty($f['run'])){$selected=(clone $q)->where('id',$f['run'])->first();if($selected){$selected->decoded=json_decode($selected->record,true);$events=DB::table('game_exploration_events')->where('player_id',$selected->player_id)->where('run_id',$selected->run_id)->orderBy('seq')->paginate(100,['*'],'event_page')->withQueryString();}}
  return response()->view('exploration-report',compact('summary','rooms','runs','selected','events','f'))->header('Cache-Control','no-store')->header('X-Robots-Tag','noindex, nofollow');
 }
}
