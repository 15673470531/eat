<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_chapter_expectations', function (Blueprint $table) {
            $table->comment('小游戏玩家章节期待，独立于业务用户和云存档');
            $table->id()->comment('期待记录编号');
            $table->foreignId('player_id')->comment('发起期待的小游戏账号')->constrained('game_players')->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter')->comment('期待的章节编号，当前开放第二章');
            $table->timestamp('created_at')->comment('首次表达期待的时间，重复点击不更新');
            $table->unique(['player_id', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_chapter_expectations');
    }
};
