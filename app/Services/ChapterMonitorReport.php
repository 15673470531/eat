<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChapterMonitorReport
{
    public function query(array $filters)
    {
        $from = CarbonImmutable::parse($filters['from'] ?? now('Asia/Shanghai')->subDays(6)->toDateString(), 'Asia/Shanghai')->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? now('Asia/Shanghai')->toDateString(), 'Asia/Shanghai')->endOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 31) {
            throw ValidationException::withMessages(['to' => '请选择不超过31天的时间范围']);
        }
        $q = DB::table('game_chapter_runs')->where('chapter', 1)->whereBetween('started_at', [$from->utc()->format('Y-m-d H:i:s'), $to->utc()->format('Y-m-d H:i:s')]);
        if (empty($filters['include_test'])) {
            $q->where('test_device', false);
        }
        if (! empty($filters['player_id'])) {
            $q->where('player_id', (int) $filters['player_id']);
        }
        if (! empty($filters['build'])) {
            $q->where('build', $filters['build']);
        }

        return [$q, $from, $to];
    }

    public function summary(array $filters): array
    {
        [$q,$from,$to] = $this->query($filters);
        $known = (clone $q)->where('has_start', true);
        $count = (clone $known)->count();
        $completed = (clone $known)->where('status', 'completed')->count();
        $first = (clone $known)->whereNotExists(function ($s) {
            $s->selectRaw('1')->from('game_chapter_runs as earlier')->whereColumn('earlier.player_id', 'game_chapter_runs.player_id')->where('earlier.has_start', true)->whereColumn('earlier.test_device', 'game_chapter_runs.test_device')->where(function ($c) {
                $c->whereColumn('earlier.started_at', '<', 'game_chapter_runs.started_at')->orWhere(function ($d) {
                    $d->whereColumn('earlier.started_at', 'game_chapter_runs.started_at')->whereColumn('earlier.id', '<', 'game_chapter_runs.id');
                });
            });
        });
        $logins = DB::table('game_player_logins')->whereBetween('occurred_at', [$from->utc()->format('Y-m-d H:i:s'), $to->utc()->format('Y-m-d H:i:s')]);
        if (! empty($filters['player_id'])) {
            $logins->where('player_id', (int) $filters['player_id']);
        }
        $loginPlayers = (clone $logins)->distinct()->count('player_id');
        $loginStarted = (clone $logins)->whereIn('player_id', (clone $known)->select('player_id'))->distinct()->count('player_id');
        $events = DB::table('game_chapter_events as e')->joinSub((clone $q)->select('player_id', 'run_id'), 'r', function ($j) {
            $j->on('e.player_id', '=', 'r.player_id')->on('e.run_id', '=', 'r.run_id');
        });
        $weapons = (clone $known)->select('start_weapon')->selectRaw("COUNT(*) as runs, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed, AVG(CASE WHEN status='completed' THEN active_seconds END) as avg_clear_seconds")->groupBy('start_weapon')->get();

        return ['scope' => '按开局时间筛选第一章对局；首局指上线监控后首次有完整开局记录的对局，测试与正式分别判断。未结束不等于失败。',
            'runs' => $count, 'partial_runs' => (clone $q)->where('has_start', false)->count(), 'players' => (clone $known)->distinct()->count('player_id'),
            'completed' => $completed, 'completion_rate' => $count ? round(100 * $completed / $count, 1) : null,
            'first_attempts' => (clone $first)->count(), 'first_attempt_completed' => (clone $first)->where('status', 'completed')->count(),
            'login_players' => $loginPlayers, 'login_players_started' => $loginStarted, 'login_scope' => '时间窗内登录/恢复登录的用户中，同窗有匹配筛选条件开局的用户；登录记录没有版本或测试标签，不作为严格版本转化率。',
            'weapons' => $weapons, 'statuses' => (clone $q)->select('status')->selectRaw('COUNT(*) as runs')->groupBy('status')->get(),
            'waves' => (clone $q)->select('max_wave', 'status')->selectRaw('COUNT(*) as runs')->groupBy('max_wave', 'status')->orderBy('max_wave')->get(),
            'death_reasons' => (clone $events)->where('event', 'death')->select('detail')->selectRaw('COUNT(*) as deaths')->groupBy('detail')->orderByDesc('deaths')->limit(30)->get(),
            'interrupted_unknown' => (clone $q)->whereIn('status', ['active', 'paused'])->where('last_occurred_at', '<', now('UTC')->subDay())->count()];
    }

    public function runs(array $filters, int $page = 1)
    {
        [$q] = $this->query($filters);

        return $q->orderByDesc('started_at')->orderByDesc('id')->paginate(30, ['*'], 'page', $page)->through(function ($r) {
            $r->state = json_decode($r->state, true);
            $r->display_status = $this->status($r);

            return $r;
        });
    }

    public function status($r): string
    {
        if (in_array($r->status, ['active', 'paused']) && CarbonImmutable::parse($r->last_occurred_at, 'UTC')->lt(now('UTC')->subDay())) {
            return 'interrupted_unknown';
        }

        return $r->status;
    }

    public function player(int $id): ?array
    {
        $p = DB::table('game_players')->where('id', $id)->select('id', 'created_at', 'last_login_at', 'tutorial_completed')->first();
        if (! $p) {
            return null;
        }

        return ['profile' => $p, 'login_history' => DB::table('game_player_logins')->where('player_id', $id)->orderByDesc('id')->limit(50)->get(),
            'all_time_runs' => DB::table('game_chapter_runs')->where('player_id', $id)->where('test_device', false)->count(),
            'first_clear' => DB::table('game_chapter_runs')->where('player_id', $id)->where('test_device', false)->where('status', 'completed')->orderBy('last_occurred_at')->first(['started_at', 'last_occurred_at', 'active_seconds', 'start_weapon', 'weapon'])];
    }

    public function timeline(int $id, int $page = 1): array
    {
        $run = DB::table('game_chapter_runs')->where('id', $id)->first();
        abort_unless($run, 404);
        $run->state = json_decode($run->state, true);
        $run->display_status = $this->status($run);
        $events = DB::table('game_chapter_events')->where('player_id', $run->player_id)->where('run_id', $run->run_id)->orderBy('seq')->paginate(100,['*'],'event_page',$page)->through(function ($e) {
            $e->state = json_decode($e->state,true);

            return $e;
        });

        return ['run' => $run, 'events' => $events];
    }
}
