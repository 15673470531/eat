<?php

namespace App\Http\Controllers\Api\GameMonitor;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChapterEventController extends Controller
{
    public function store(Request $request)
    {
        $weapons = ['sword', 'boomerang', 'knife'];
        $data = $request->validate([
            'events' => 'required|array|min:1|max:20',
            'events.*' => 'required|array:run_id,seq,chapter,event,detail,build,test_device,occurred_at,state',
            'events.*.run_id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'events.*.seq' => 'required|integer|between:1,10000000', 'events.*.chapter' => ['required', Rule::in([1])],
            'events.*.event' => ['required', Rule::in(['start', 'resume', 'heartbeat', 'wave_complete', 'weapon_change', 'card_select', 'elite_kill', 'boss_kill', 'death', 'revive', 'pause', 'background', 'foreground', 'leave', 'restart', 'complete'])],
            'events.*.detail' => 'nullable|string|max:96', 'events.*.build' => 'required|string|max:64', 'events.*.test_device' => 'required|boolean',
            'events.*.occurred_at' => ['required', 'date_format:Y-m-d\TH:i:s\Z', 'after_or_equal:'.now('UTC')->subDays(30)->format('Y-m-d\TH:i:s\Z'), 'before_or_equal:'.now('UTC')->addMinutes(10)->format('Y-m-d\TH:i:s\Z')],
            'events.*.state' => 'required|array:status,started_at,complete_start,start_weapon,stage,wave,completed_waves,weapon,mod,cards,awakened,active_seconds,kills,hits,revives,elite_kills,boss_kills,hp,dropped_events',
            'events.*.state.status' => ['required', Rule::in(['active', 'paused', 'dead', 'exited', 'restarted', 'completed'])],
            'events.*.state.started_at' => ['required', 'date_format:Y-m-d\TH:i:s\Z', 'before_or_equal:'.now('UTC')->addMinutes(10)->format('Y-m-d\TH:i:s\Z')],
            'events.*.state.complete_start' => 'required|boolean', 'events.*.state.start_weapon' => ['required', Rule::in($weapons)],
            'events.*.state.weapon' => ['required', Rule::in($weapons)], 'events.*.state.mod' => ['required', Rule::in(['original', 'giant', 'swift', 'return', 'twin', 'pierce', 'burst'])],
            'events.*.state.stage' => 'required|integer|between:1,100', 'events.*.state.wave' => 'required|integer|between:1,100',
            'events.*.state.completed_waves' => 'present|array|max:100', 'events.*.state.completed_waves.*' => 'integer|between:1,100',
            'events.*.state.cards' => 'present|array|max:40', 'events.*.state.cards.*' => 'integer|between:0,3',
            'events.*.state.awakened' => 'required|boolean', 'events.*.state.active_seconds' => 'required|integer|between:0,604800',
            'events.*.state.kills' => 'required|integer|between:0,1000000', 'events.*.state.hits' => 'required|integer|between:0,1000000',
            'events.*.state.revives' => 'required|integer|between:0,10000', 'events.*.state.elite_kills' => 'required|integer|between:0,10000',
            'events.*.state.boss_kills' => 'required|integer|between:0,10000', 'events.*.state.hp' => 'required|integer|between:0,100000',
            'events.*.state.dropped_events' => 'required|integer|between:0,10000000',
        ]);
        foreach ($data['events'] as $i => $e) {
            foreach (array_keys($e['state']['cards']) as $key) {
                if (! preg_match('/^[a-z0-9_]{1,48}$/', (string) $key)) {
                    throw ValidationException::withMessages(["events.$i.state.cards" => '无效卡片标识']);
                }
            }
            if ($e['state']['started_at'] > $e['occurred_at']) {
                throw ValidationException::withMessages(["events.$i.state.started_at" => '开局不能晚于事件']);
            }
        }
        $player = $request->attributes->get('game_player')->id;
        $ack = DB::transaction(function () use ($data, $player) {
            $ack = [];
            foreach ($data['events'] as $e) {
                $s = $e['state'];
                $now = now('UTC')->format('Y-m-d H:i:s');
                $at = CarbonImmutable::parse($e['occurred_at'])->utc()->format('Y-m-d H:i:s');
                $start = CarbonImmutable::parse($s['started_at'])->utc()->format('Y-m-d H:i:s');
                DB::table('game_chapter_runs')->insertOrIgnore(['player_id' => $player, 'run_id' => $e['run_id'], 'chapter' => 1, 'build' => $e['build'], 'test_device' => $e['test_device'],
                    'has_start' => false, 'start_weapon' => $s['start_weapon'], 'weapon' => $s['weapon'], 'status' => $s['status'], 'started_at' => $start, 'last_occurred_at' => $at, 'received_at' => $now, 'state' => json_encode($s)]);
                $run = DB::table('game_chapter_runs')->where('player_id', $player)->where('run_id', $e['run_id'])->lockForUpdate()->first();
                $inserted = DB::table('game_chapter_events')->insertOrIgnore(['player_id' => $player, 'run_id' => $e['run_id'], 'seq' => $e['seq'], 'event' => $e['event'], 'detail' => $e['detail'] ?? '',
                    'build' => $e['build'], 'test_device' => $e['test_device'], 'occurred_at' => $at, 'received_at' => $now, 'state' => json_encode($s)]);
                if ($inserted) {
                    $values = ['event_count' => $run->event_count + 1, 'test_device' => $run->test_device || $e['test_device'],
                        'has_start' => $run->has_start || ($e['event'] === 'start' && $s['complete_start']), 'max_wave' => max($run->max_wave, $s['wave'])];
                    if ($e['event'] === 'start') {
                        $values += ['started_at' => $start, 'start_weapon' => $s['start_weapon'], 'build' => $e['build']];
                    }
                    if ($e['seq'] > $run->last_seq) {
                        if ($run->status === 'completed') {
                            $s['status'] = 'completed';
                        }
                        $values += ['last_seq' => $e['seq'], 'weapon' => $s['weapon'], 'status' => $s['status'], 'active_seconds' => $s['active_seconds'],
                            'revives' => $s['revives'], 'kills' => $s['kills'], 'last_occurred_at' => $at, 'received_at' => $now, 'state' => json_encode($s)];
                    }
                    DB::table('game_chapter_runs')->where('id', $run->id)->update($values);
                }
                $ack[] = ['run_id' => $e['run_id'], 'seq' => $e['seq']];
            }

return $ack;
        });

        return response()->json(['data' => ['acknowledged' => $ack]]);
    }
}
