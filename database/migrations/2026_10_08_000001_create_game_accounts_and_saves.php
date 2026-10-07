<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_players', function (Blueprint $t) {
            $t->id()->comment('小游戏账号主键，不关联 eatWhat 用户');
            $t->string('appid', 64)->comment('小游戏 AppID');
            $t->string('openid', 128)->comment('服务端向微信换取的用户标识');
            $t->boolean('tutorial_completed')->default(false)->comment('序章完成且首次成果已导入');
            $t->timestamp('last_login_at')->nullable()->comment('最近微信登录时间');
            $t->timestamp('created_at')->nullable()->comment('创建时间');
            $t->timestamp('updated_at')->nullable()->comment('更新时间');
            $t->unique(['appid', 'openid']);
        });
        Schema::create('game_player_sessions', function (Blueprint $t) {
            $t->char('token_hash', 64)->primary()->comment('随机登录凭证的 SHA256，原文不入库');
            $t->foreignId('player_id')->comment('小游戏账号')->constrained('game_players')->cascadeOnDelete();
            $t->timestamp('expires_at')->index()->comment('登录凭证有效期');
        });
        Schema::create('game_player_saves', function (Blueprint $t) {
            $t->foreignId('player_id')->primary()->comment('小游戏账号，一人一份长期进度')->constrained('game_players')->cascadeOnDelete();
            $t->unsignedInteger('revision')->default(0)->comment('服务端版本号，防止旧设备覆盖');
            $t->json('payload')->comment('长期进度：熟练度、装备、金币、章节');
            $t->uuid('request_id')->nullable()->comment('最后一次成功保存的幂等请求编号');
            $t->char('request_hash', 64)->nullable()->comment('最后保存请求摘要，拒绝编号被复用于不同内容');
            $t->timestamp('updated_at')->nullable()->comment('最近保存时间');
        });
        Schema::create('game_save_backups', function (Blueprint $t) {
            $t->id()->comment('备份编号');
            $t->foreignId('player_id')->comment('所属小游戏账号')->constrained('game_players')->cascadeOnDelete();
            $t->unsignedInteger('revision')->comment('备份对应版本');
            $t->json('payload')->comment('被替换的长期存档');
            $t->timestamp('created_at')->nullable()->comment('备份时间');
        });
    }

    public function down(): void
    {
        foreach (['game_save_backups', 'game_player_saves', 'game_player_sessions', 'game_players'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
