<?php

namespace App\Http\Controllers\Api\GameAnalytics;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    private function query(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'build' => ['sometimes', 'string', 'max:64'],
            'include_test' => ['sometimes', 'boolean'],
            'event_name' => ['sometimes', Rule::in(config('game_analytics.events'))],
            'run_id' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'player_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $from = CarbonImmutable::parse($filters['from'] ?? CarbonImmutable::now('Asia/Shanghai')->subDays(6)->toDateString(), 'Asia/Shanghai')->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? CarbonImmutable::now('Asia/Shanghai')->toDateString(), 'Asia/Shanghai')->startOfDay();
        if ($to->lessThan($from) || $from->diffInDays($to) > 30) {
            throw ValidationException::withMessages(['to' => '查询范围应为 1 至 31 天，结束日期不得早于开始日期']);
        }
        $query = DB::table('game_analytics_events')->where('game_id', config('game_analytics.game_id'))
            ->where('occurred_at', '>=', $from->utc()->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $to->addDay()->utc()->format('Y-m-d H:i:s'));
        if (! ($filters['include_test'] ?? false)) {
            $query->where('test_device', false);
        }
        foreach (['build', 'event_name', 'run_id', 'player_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $field === 'player_id' ? strtolower($filters[$field]) : $filters[$field]);
            }
        }

        return [$query, ['from' => $from->toDateString(), 'to' => $to->toDateString(),
            'timezone' => 'Asia/Shanghai', 'include_test' => (bool) ($filters['include_test'] ?? false)]];
    }

    public function summary(Request $request): JsonResponse
    {
        [$query, $range] = $this->query($request);
        $events = (clone $query)->select('event_name')->selectRaw('COUNT(*) as events, COUNT(DISTINCT player_id) as players')
            ->groupBy('event_name')->orderBy('event_name')->get();
        $waves = (clone $query)->whereIn('event_name', ['kdtl_wave_complete', 'kdtl_run_death'])
            ->select('stage', 'wave', 'event_name')->selectRaw('COUNT(*) as events, COUNT(DISTINCT player_id) as players')
            ->groupBy('stage', 'wave', 'event_name')->orderBy('stage')->orderBy('wave')->get();
        $deaths = (clone $query)->where('event_name', 'kdtl_run_death')->select('detail')->selectRaw('COUNT(*) as events')
            ->groupBy('detail')->orderByDesc('events')->limit(50)->get();

        return response()->json(['code' => 0, 'data' => [
            'range' => $range,
            'scope' => 'Events occurring in this window; not a start-cohort conversion funnel.',
            'events_total' => (clone $query)->count(),
            'players' => (clone $query)->distinct()->count('player_id'),
            'runs' => DB::query()->fromSub((clone $query)->select('player_id', 'run_id')->distinct(), 'runs')->count(),
            'events' => $events, 'waves' => $waves, 'death_reasons' => $deaths,
        ]]);
    }

    public function events(Request $request): JsonResponse
    {
        [$query, $range] = $this->query($request);

        return response()->json(['code' => 0, 'data' => [
            'range' => $range,
            'items' => $query->orderByDesc('id')->paginate((int) $request->input('per_page', 50)),
            'timestamp_timezone' => 'UTC',
        ]]);
    }
}
