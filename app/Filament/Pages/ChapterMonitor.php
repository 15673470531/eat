<?php

namespace App\Filament\Pages;

use App\Services\ChapterMonitorReport;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class ChapterMonitor extends Page
{
    protected static ?string $title = '第一章监控';

    protected string $view = 'filament.pages.chapter-monitor';

    public string $from = '';

    public string $to = '';

    public string $playerId = '';

    public string $build = '';

    public bool $includeTest = false;

    #[Locked]
    public array $appliedFilters = [];

    public int $runPage = 1;

    public ?int $selectedRun = null;

    public int $eventPage = 1;

    public static function canAccess(): bool
    {
        return auth()->check() && (int) auth()->user()->getRawOriginal('is_admin') === 1;
    }

    public function mount(): void
    {
        $this->from = now('Asia/Shanghai')->subDays(6)->toDateString();
        $this->to = now('Asia/Shanghai')->toDateString();
        $this->appliedFilters = $this->validateFilters();
    }

    public function search(): void
    {
        $f = $this->validateFilters();
        app(ChapterMonitorReport::class)->query($f);
        $this->appliedFilters = $f;
        $this->runPage = 1;
        $this->selectedRun = null;
        $this->eventPage = 1;
    }

    private function validateFilters(): array
    {
        $this->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d', 'playerId' => 'nullable|integer|min:1', 'build' => 'string|max:64']);

        return ['from' => $this->from, 'to' => $this->to, 'player_id' => $this->playerId, 'build' => $this->build, 'include_test' => $this->includeTest];
    }

    public function openRun(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->selectedRun = $id;
        $this->eventPage = 1;
    }

    public function user(int $id): void
    {
        $this->playerId = (string) $id;
        $this->search();
    }

    public function changePage(int $delta): void
    {
        $this->runPage = max(1, $this->runPage + $delta);
    }

    public function changeEventPage(int $delta): void
    {
        $this->eventPage = max(1, $this->eventPage + $delta);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $s = app(ChapterMonitorReport::class);
        $f = $this->appliedFilters;

        return ['summary' => $s->summary($f), 'runs' => $s->runs($f, $this->runPage), 'player' => ! empty($f['player_id']) ? $s->player((int) $f['player_id']) : null,
            'timeline' => $this->selectedRun ? $s->timeline($this->selectedRun, $this->eventPage) : null];
    }
}
