<?php

namespace App\Http\Controllers\Api\GameAnalytics;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $id = ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'];
        $data = $request->validate([
            'game_id' => ['required', Rule::in([config('game_analytics.game_id')])],
            'player_id' => ['required', 'uuid'],
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*' => ['required', 'array:run_id,seq,event_name,build,stage,wave,weapon,seconds,detail,test_device,previous_run_id,occurred_at'],
            'events.*.run_id' => $id,
            'events.*.seq' => ['required', 'integer', 'min:1', 'max:10000000'],
            'events.*.event_name' => ['required', Rule::in(config('game_analytics.events'))],
            'events.*.build' => ['required', 'string', 'max:64'],
            'events.*.stage' => ['required', 'integer', 'between:1,10000'],
            'events.*.wave' => ['required', 'integer', 'between:1,10000'],
            'events.*.weapon' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_-]+$/'],
            'events.*.seconds' => ['required', 'integer', 'between:0,604800'],
            'events.*.detail' => ['nullable', 'string', 'max:64'],
            'events.*.test_device' => ['required', 'boolean'],
            'events.*.previous_run_id' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            // Explicit UTC makes aggregation independent of the deployed application's timezone.
            'events.*.occurred_at' => ['required', 'date_format:Y-m-d\TH:i:s\Z',
                'after_or_equal:'.CarbonImmutable::now('UTC')->subDays(30)->format('Y-m-d\TH:i:s\Z'),
                'before_or_equal:'.CarbonImmutable::now('UTC')->addMinutes(10)->format('Y-m-d\TH:i:s\Z')],
        ]);
        $player = strtolower($data['player_id']);
        $rows = [];
        $ack = [];
        foreach ($data['events'] as $event) {
            $key = hash('sha256', implode('|', [$data['game_id'], $player, $event['run_id'], $event['seq']]));
            $rows[$key] ??= [
                'event_key' => $key, 'game_id' => $data['game_id'], 'player_id' => $player,
                'run_id' => $event['run_id'], 'seq' => $event['seq'], 'event_name' => $event['event_name'],
                'build' => $event['build'], 'stage' => $event['stage'], 'wave' => $event['wave'],
                'weapon' => $event['weapon'], 'seconds' => $event['seconds'],
                'detail' => $event['detail'] ?? '', 'test_device' => (bool) $event['test_device'],
                'previous_run_id' => $event['previous_run_id'] ?? '',
                'occurred_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $event['occurred_at'], 'UTC')->format('Y-m-d H:i:s'),
                'received_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            ];
            $ack[$key] = ['run_id' => $event['run_id'], 'seq' => (int) $event['seq']];
        }
        // Atomic batch. Conflict is a no-op, preserving the first stored event, including its test flag.
        DB::transaction(fn () => DB::table('game_analytics_events')->upsert(array_values($rows), ['event_key'], ['event_key']));

        return response()->json(['code' => 0, 'data' => ['acknowledged' => array_values($ack)]]);
    }
}
