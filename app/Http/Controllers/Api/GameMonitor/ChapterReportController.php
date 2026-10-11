<?php

namespace App\Http\Controllers\Api\GameMonitor;

use App\Http\Controllers\Controller;
use App\Services\ChapterMonitorReport;
use Illuminate\Http\Request;

class ChapterReportController extends Controller
{
    private function filters(Request $r): array
    {
        return $r->validate(['from' => 'sometimes|date_format:Y-m-d', 'to' => 'sometimes|date_format:Y-m-d', 'player_id' => 'sometimes|integer|min:1', 'build' => 'sometimes|string|max:64', 'include_test' => 'sometimes|boolean', 'page' => 'sometimes|integer|between:1,100000']);
    }

    public function summary(Request $r, ChapterMonitorReport $s)
    {
        return response()->json(['data' => $s->summary($this->filters($r))]);
    }

    public function runs(Request $r, ChapterMonitorReport $s)
    {
        return response()->json(['data' => $s->runs($this->filters($r), (int) $r->input('page', 1))]);
    }

    public function player(int $id, ChapterMonitorReport $s)
    {
        $p = $s->player($id);
        abort_unless($p, 404);

        return response()->json(['data' => $p]);
    }

    public function timeline(int $id, Request $r, ChapterMonitorReport $s)
    {
        $r->validate(['event_page' => 'sometimes|integer|between:1,100000']);

        return response()->json(['data' => $s->timeline($id, (int) $r->input('event_page', 1))]);
    }
}
