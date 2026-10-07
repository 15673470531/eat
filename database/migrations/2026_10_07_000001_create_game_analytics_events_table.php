<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_analytics_events', function (Blueprint $table) {
            $table->comment('小游戏行为事件：独立统计存储，不关联原业务用户');
            $table->id()->comment('自增主键');
            $table->char('event_key', 64)->unique()->comment('事件唯一键：game_id、player_id、run_id、seq 的 SHA-256，用于重复上报去重');
            $table->string('game_id', 32)->comment('游戏标识，砍到天亮固定为 kdtl');
            $table->uuid('player_id')->comment('客户端本地随机玩家 UUID；清缓存或换设备后可能变化，不是微信 openid');
            $table->string('run_id', 64)->comment('对局编号；继续存档保持不变，新开一局重新生成');
            $table->unsignedInteger('seq')->comment('局内事件递增序号；重试使用原序号');
            $table->string('event_name', 32)->comment('事件名称，如 kdtl_run_start 开局、kdtl_run_death 死亡');
            $table->string('build', 64)->comment('客户端上报的游戏版本标记');
            $table->unsignedSmallInteger('stage')->comment('事件发生时的关卡编号，从 1 开始');
            $table->unsignedSmallInteger('wave')->comment('事件发生时的波次；波次完成事件记录刚完成的波次');
            $table->string('weapon', 32)->comment('事件发生时所持武器标识，如 sword');
            $table->unsignedInteger('seconds')->comment('本局累计游戏模拟时长，单位秒；不等同于平台停留时长');
            $table->string('detail', 64)->default('')->comment('事件补充信息，如死亡原因或结算选择；无内容为空字符串');
            $table->boolean('test_device')->default(false)->comment('测试数据标记：0 正式、1 测试；默认报表排除测试数据');
            $table->string('previous_run_id', 64)->default('')->comment('同一进程内结算后新开的对局所关联的上一局编号；无关联为空字符串');
            $table->dateTime('occurred_at')->comment('客户端事件发生时间，UTC');
            $table->dateTime('received_at')->comment('服务端首次接收并写入时间，UTC；重复上报不更新');
            $table->index(['game_id', 'occurred_at'], 'ga_game_time');
            $table->index(['game_id', 'player_id', 'run_id'], 'ga_player_run');
            $table->index(['game_id', 'event_name', 'occurred_at'], 'ga_event_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_analytics_events');
    }
};
