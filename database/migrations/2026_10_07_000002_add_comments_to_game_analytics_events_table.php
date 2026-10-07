<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $columns = [
        'id' => ['BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', '自增主键'],
        'event_key' => ['CHAR(64) NOT NULL', '事件唯一键：game_id、player_id、run_id、seq 的 SHA-256，用于重复上报去重'],
        'game_id' => ['VARCHAR(32) NOT NULL', '游戏标识，砍到天亮固定为 kdtl'],
        'player_id' => ['CHAR(36) NOT NULL', '客户端本地随机玩家 UUID；清缓存或换设备后可能变化，不是微信 openid'],
        'run_id' => ['VARCHAR(64) NOT NULL', '对局编号；继续存档保持不变，新开一局重新生成'],
        'seq' => ['INT UNSIGNED NOT NULL', '局内事件递增序号；重试使用原序号'],
        'event_name' => ['VARCHAR(32) NOT NULL', '事件名称，如 kdtl_run_start 开局、kdtl_run_death 死亡'],
        'build' => ['VARCHAR(64) NOT NULL', '客户端上报的游戏版本标记'],
        'stage' => ['SMALLINT UNSIGNED NOT NULL', '事件发生时的关卡编号，从 1 开始'],
        'wave' => ['SMALLINT UNSIGNED NOT NULL', '事件发生时的波次；波次完成事件记录刚完成的波次'],
        'weapon' => ['VARCHAR(32) NOT NULL', '事件发生时所持武器标识，如 sword'],
        'seconds' => ['INT UNSIGNED NOT NULL', '本局累计游戏模拟时长，单位秒；不等同于平台停留时长'],
        'detail' => ['VARCHAR(64) NOT NULL DEFAULT \'\'', '事件补充信息，如死亡原因或结算选择；无内容为空字符串'],
        'test_device' => ['TINYINT(1) NOT NULL DEFAULT 0', '测试数据标记：0 正式、1 测试；默认报表排除测试数据'],
        'previous_run_id' => ['VARCHAR(64) NOT NULL DEFAULT \'\'', '同一进程内结算后新开的对局所关联的上一局编号；无关联为空字符串'],
        'occurred_at' => ['DATETIME NOT NULL', '客户端事件发生时间，UTC'],
        'received_at' => ['DATETIME NOT NULL', '服务端首次接收并写入时间，UTC；重复上报不更新'],
    ];

    public function up(): void
    {
        $this->applyComments(true);
    }

    public function down(): void
    {
        // Remove comments only; never drop the table or its event data.
        $this->applyComments(false);
    }

    private function applyComments(bool $enabled): void
    {
        // SQLite has no persistent column comments; production uses MySQL.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $clauses = [];
        foreach ($this->columns as $name => [$definition, $comment]) {
            $literal = DB::connection()->getPdo()->quote($enabled ? $comment : '');
            $clauses[] = "MODIFY COLUMN `{$name}` {$definition} COMMENT {$literal}";
        }
        $tableComment = DB::connection()->getPdo()->quote($enabled ? '小游戏行为事件：独立统计存储，不关联原业务用户' : '');
        $clauses[] = "COMMENT = {$tableComment}";
        DB::statement('ALTER TABLE `game_analytics_events` '.implode(', ', $clauses));
    }
};
