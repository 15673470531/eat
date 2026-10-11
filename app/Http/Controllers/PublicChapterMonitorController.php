<?php

namespace App\Http\Controllers;

use App\Services\ChapterMonitorReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublicChapterMonitorController extends Controller
{
    public function __invoke(Request $request, ChapterMonitorReport $report)
    {
        try {
            $filters = Validator::make($request->query(), [
                'from' => 'nullable|date_format:Y-m-d',
                'to' => 'nullable|date_format:Y-m-d',
                'player_id' => 'nullable|integer|min:1',
                'build' => 'nullable|string|max:64',
                'include_test' => 'nullable|boolean',
                'page' => 'nullable|integer|between:1,100000',
                'run_id' => 'nullable|integer|min:1',
                'event_page' => 'nullable|integer|between:1,100000',
            ])->validate();
            $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
            $summary = $report->summary($filters);
        } catch (ValidationException $exception) {
            return response()->view('chapter-monitor', ['errorMessages' => $exception->errors()], 422)
                ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $data = [
            'publicMode' => true,
            'summary' => $summary,
            'runs' => $report->runs($filters, (int) ($filters['page'] ?? 1)),
            'player' => ! empty($filters['player_id']) ? $report->player((int) $filters['player_id']) : null,
            'timeline' => ! empty($filters['run_id']) ? $report->timeline((int) $filters['run_id'], (int) ($filters['event_page'] ?? 1)) : null,
            'from' => $filters['from'] ?? now('Asia/Shanghai')->subDays(6)->toDateString(),
            'to' => $filters['to'] ?? now('Asia/Shanghai')->toDateString(),
            'playerId' => $filters['player_id'] ?? '',
            'build' => $filters['build'] ?? '',
            'includeTest' => ! empty($filters['include_test']),
            'monitorUrl' => fn (array $changes) => route('chapter-monitor.public', array_filter(array_replace($filters, $changes), fn ($value) => $value !== null && $value !== '')),
        ];

        return response()->view('chapter-monitor', $data)
            ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
