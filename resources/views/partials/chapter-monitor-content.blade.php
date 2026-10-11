@php
$publicMode=$publicMode??false;
$status=['active'=>'进行中','paused'=>'暂停待续','dead'=>'死亡结束（可复活）','exited'=>'主动退出（可续玩）','restarted'=>'主动重开','completed'=>'第一章通关','interrupted_unknown'=>'中断未结算／结果未知'];
$weapons=['sword'=>'长剑','boomerang'=>'回旋镖','knife'=>'飞刀'];
$events=['start'=>'开始第一章','resume'=>'继续对局','heartbeat'=>'进度快照','wave_complete'=>'完成波次','weapon_change'=>'换武器','card_select'=>'选卡','elite_kill'=>'击败精英','boss_kill'=>'击败Boss','death'=>'死亡','revive'=>'复活','pause'=>'暂停','background'=>'切后台','foreground'=>'回前台','leave'=>'主动退出','restart'=>'重开','complete'=>'第一章通关'];
$mods=['original'=>'原型','sword:giant'=>'剑境·广','sword:swift'=>'剑境·疾','boomerang:return'=>'镖境·阔','boomerang:twin'=>'镖境·疾','knife:pierce'=>'刀境·远','knife:burst'=>'刀境·疾'];
$cards=['dmg'=>'锋利','hp'=>'坚韧','spd'=>'轻身','dash'=>'疾影','life'=>'噬血','thorns'=>'尖甲','trail'=>'灼痕','invuln'=>'不坏之躯','range'=>'长刃','blades'=>'旋刃','aspd'=>'迅捷','spin'=>'续航','sword_sheathe'=>'收势','exec'=>'处决','boom_grow_reach'=>'阔幅','boom_grow_count'=>'连旋','boom_range'=>'远旋','boom_chase'=>'追风','boom_ready'=>'收势','boom_echo'=>'回响','knife_volley'=>'连掷','knife_pierce'=>'贯刃','knife_grow_reach'=>'长锋','knife_range'=>'远锋','knife_ready'=>'装填','knife_bounce'=>'弹射'];
@endphp
<style>.cm-box{border:1px solid #94a3b84a;border-radius:12px;padding:18px;margin-bottom:16px}.cm-grid{display:flex;flex-wrap:wrap;gap:14px}.cm-grid label{display:flex;flex-direction:column;gap:6px}.cm-grid input{border:1px solid #94a3b8;border-radius:6px;padding:7px;background:transparent;max-width:210px}.cm-table{width:100%;border-collapse:collapse;font-size:13px}.cm-table th,.cm-table td{padding:10px 8px;text-align:left;border-bottom:1px solid #94a3b833;vertical-align:top}.cm-scroll{overflow:auto}.cm-note{font-size:12px;color:#64748b;margin:8px 0}.cm-action{color:#6366f1;cursor:pointer;text-decoration:underline}.dark .cm-note{color:#94a3b8}.cm-metric{font-size:13px;flex:1;min-width:130px}.cm-metric strong{font-size:26px;display:block}.cm-head{font-weight:700;margin-bottom:10px}.cm-buttons{display:flex;gap:18px;margin-top:12px}</style>
<div class="cm-box">
<form @if($publicMode) method="get" action="{{ route('chapter-monitor.public') }}" @else wire:submit="search" @endif class="cm-grid">
<label>开局日期（北京时间）<input type="date" wire:model="from" @if($publicMode) name="from" value="{{ $from }}" @endif></label><label>至<input type="date" wire:model="to" @if($publicMode) name="to" value="{{ $to }}" @endif></label>
<label>小游戏用户ID<input type="number" min="1" wire:model="playerId" @if($publicMode) name="player_id" value="{{ $playerId }}" @endif placeholder="留空查看全部"></label><label>开局版本<input wire:model="build" @if($publicMode) name="build" value="{{ $build }}" @endif placeholder="全部版本"></label>
<label><span>包含测试对局</span><input type="checkbox" wire:model="includeTest" @if($publicMode) name="include_test" value="1" @checked($includeTest) @endif></label><button class="cm-action" type="submit">查询 / 刷新</button>
</form>
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
<p class="cm-note">{{ $summary['scope'] }} 查询范围最多31天。测试数据默认排除。页面时间戳为UTC；日期筛选为北京时间。</p>
</div>
<div class="cm-box cm-grid">
<div class="cm-metric">完整开局<strong>{{ $summary['runs'] }}</strong>{{ $summary['players'] }}位用户</div>
<div class="cm-metric">第一章通关<strong>{{ $summary['completed'] }}</strong>观察通关比例 {{ $summary['completion_rate'] ?? '—' }}%</div>
<div class="cm-metric">监控内首次挑战<strong>{{ $summary['first_attempt_completed'] }}/{{ $summary['first_attempts'] }}</strong>通关 / 首次挑战</div>
<div class="cm-metric">超过24小时无后续<strong>{{ $summary['interrupted_unknown'] }}</strong>结果未知，不计作死亡</div>
<div class="cm-metric">缺少开局的续玩<strong>{{ $summary['partial_runs'] }}</strong>不计入完整开局通关率</div>
</div>
<div class="cm-box"><h2 class="cm-head">登录与开玩</h2><p>窗口内登录用户 {{ $summary['login_players'] }}，其中有匹配开局记录 {{ $summary['login_players_started'] }}。</p><p class="cm-note">{{ $summary['login_scope'] }}</p></div>
@if($player)
<div class="cm-box"><h2 class="cm-head">用户 #{{ $player['profile']->id }}</h2><p>首次注册登录 {{ $player['profile']->created_at }} · 最近微信登录 {{ $player['profile']->last_login_at }} · 累计非测试记录 {{ $player['all_time_runs'] }}局</p>
@if($player['first_clear'])<p>首次记录通关：{{ $player['first_clear']->last_occurred_at }}，有效游玩 {{ $player['first_clear']->active_seconds }}秒。</p>@else<p>暂无通关记录。</p>@endif
<details><summary class="cm-action">最近50次登录/恢复登录</summary>@foreach($player['login_history'] as $login)<p>{{ $login->occurred_at }} · {{ $login->source==='login'?'微信登录':'恢复登录' }}</p>@endforeach</details></div>
@elseif($playerId)<div class="cm-box">未找到该小游戏用户ID。</div>@endif
<div class="cm-box"><h2 class="cm-head">开局武器对照</h2><table class="cm-table"><thead><tr><th>武器</th><th>完整开局</th><th>通关</th><th>通关平均有效秒数</th></tr></thead><tbody>@foreach($summary['weapons'] as $row)<tr><td>{{ $weapons[$row->start_weapon]??$row->start_weapon }}</td><td>{{ $row->runs }}</td><td>{{ $row->completed }}</td><td>{{ $row->avg_clear_seconds!==null?round($row->avg_clear_seconds):'—' }}</td></tr>@endforeach</tbody></table><p class="cm-note">按开局武器归类；局内换武器请查看时间线。建议选同版本比较，避免不同平衡版本混算。</p></div>
<div class="cm-box"><h2 class="cm-head">波次停留与死亡原因</h2><div class="cm-grid"><div>@foreach($summary['waves'] as $row)<p>到达第{{ $row->max_wave }}波 · {{ $status[$row->status]??$row->status }}：{{ $row->runs }}局</p>@endforeach</div><div>@foreach($summary['death_reasons'] as $row)<p>{{ $row->detail?:'未知来源' }}：{{ $row->deaths }}次死亡</p>@endforeach</div></div></div>
<div class="cm-box"><h2 class="cm-head">第一章对局</h2><div class="cm-scroll"><table class="cm-table"><thead><tr><th>用户 / 局号</th><th>开局UTC / 版本</th><th>状态</th><th>到达波次</th><th>武器：开局 → 当前</th><th>有效秒数 / 击杀 / 复活</th><th></th></tr></thead><tbody>
@forelse($runs as $run)<tr><td>@if($publicMode)<a class="cm-action" href="{{ $monitorUrl(['player_id'=>$run->player_id,'page'=>1,'run_id'=>null,'event_page'=>null]) }}">#{{ $run->player_id }}</a>@else<button class="cm-action" wire:click="user({{ $run->player_id }})">#{{ $run->player_id }}</button>@endif<br>{{ $run->run_id }}</td><td>{{ $run->started_at }}<br>{{ $run->build }}{{ $run->test_device?'（测试）':'' }}</td><td>{{ $status[$run->display_status]??$run->display_status }}{{ !$run->has_start?' · 开局缺失':'' }}</td><td>{{ $run->max_wave }}</td><td>{{ $weapons[$run->start_weapon]??$run->start_weapon }} → {{ $weapons[$run->weapon]??$run->weapon }}</td><td>{{ $run->active_seconds }}秒 / {{ $run->kills }} / {{ $run->revives }}</td><td>@if($publicMode)<a class="cm-action" href="{{ $monitorUrl(['run_id'=>$run->id,'event_page'=>1]) }}">查看过程</a>@else<button class="cm-action" wire:click="openRun({{ $run->id }})">查看过程</button>@endif</td></tr>@empty<tr><td colspan="7">当前筛选条件下暂无记录；旧版本数据不会自动补出。</td></tr>@endforelse
</tbody></table></div><div class="cm-buttons">@if($runs->currentPage()>1)@if($publicMode)<a class="cm-action" href="{{ $monitorUrl(['page'=>$runs->currentPage()-1]) }}">上一页</a>@else<button class="cm-action" wire:click="changePage(-1)">上一页</button>@endif
@endif<span>第{{ $runs->currentPage() }} / {{ $runs->lastPage() }}页</span>@if($runs->hasMorePages())@if($publicMode)<a class="cm-action" href="{{ $monitorUrl(['page'=>$runs->currentPage()+1]) }}">下一页</a>@else<button class="cm-action" wire:click="changePage(1)">下一页</button>@endif
@endif</div></div>
@if($timeline)
@php($s=$timeline['run']->state)
<div class="cm-box"><h2 class="cm-head">对局过程 · 用户 #{{ $timeline['run']->player_id }} · {{ $timeline['run']->run_id }}</h2>
<p>{{ $status[$timeline['run']->display_status]??$timeline['run']->display_status }} · {{ $mods[$s['weapon'].':'.$s['mod']]??'原型' }}{{ $s['awakened']?' · 已大成':'' }} · 受伤{{ $s['hits'] }}次 · 精英{{ $s['elite_kills'] }} · Boss{{ $s['boss_kills'] }}</p>
<p>已完成波次：{{ implode('、',$s['completed_waves'])?:'暂无' }}；最终卡片：@forelse($s['cards'] as $card=>$level) {{ $cards[$card]??$card }} Lv{{ $level }}； @empty 无 @endforelse</p>
@if($s['dropped_events'])<p>客户端报告部分缓存事件丢失：{{ $s['dropped_events'] }}条；时间线可能不完整。</p>@endif
<div class="cm-scroll"><table class="cm-table"><thead><tr><th>序号 / UTC时间</th><th>事件</th><th>波次 / 有效秒数</th><th>武器 / 感悟</th><th>详细信息</th></tr></thead><tbody>
@foreach($timeline['events'] as $e)<tr><td>{{ $e->seq }} · {{ $e->occurred_at }}</td><td>{{ $events[$e->event]??$e->event }}</td><td>{{ $e->state['wave'] }}波 / {{ $e->state['active_seconds'] }}秒</td><td>{{ $weapons[$e->state['weapon']]??$e->state['weapon'] }} / {{ $mods[$e->state['weapon'].':'.$e->state['mod']]??'原型' }}</td><td>{{ $e->detail }}@if($e->event==='card_select')@foreach($e->state['cards'] as $card=>$level) {{ $cards[$card]??$card }} Lv{{ $level }}；@endforeach @endif</td></tr>@endforeach
</tbody></table></div><div class="cm-buttons">@if($timeline['events']->currentPage()>1)@if($publicMode)<a class="cm-action" href="{{ $monitorUrl(['event_page'=>$timeline['events']->currentPage()-1]) }}">上一页事件</a>@else<button class="cm-action" wire:click="changeEventPage(-1)">上一页事件</button>@endif
@endif@if($timeline['events']->hasMorePages())@if($publicMode)<a class="cm-action" href="{{ $monitorUrl(['event_page'=>$timeline['events']->currentPage()+1]) }}">下一页事件</a>@else<button class="cm-action" wire:click="changeEventPage(1)">下一页事件</button>@endif
@endif</div>
</div>
@endif

