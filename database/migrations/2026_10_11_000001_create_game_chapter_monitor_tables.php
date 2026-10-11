<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_chapter_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('player_id');
            $t->string('run_id', 64);
            $t->unsignedTinyInteger('chapter')->default(1);
            $t->string('build', 64);
            $t->boolean('test_device')->default(false);
            $t->boolean('has_start')->default(false);
            $t->string('start_weapon', 32);
            $t->string('weapon', 32);
            $t->string('status', 24);
            $t->unsignedInteger('last_seq')->default(0);
            $t->unsignedInteger('event_count')->default(0);
            $t->unsignedInteger('max_wave')->default(1);
            $t->unsignedInteger('active_seconds')->default(0);
            $t->unsignedInteger('revives')->default(0);
            $t->unsignedInteger('kills')->default(0);
            $t->dateTime('started_at');
            $t->dateTime('last_occurred_at');
            $t->dateTime('received_at');
            $t->longText('state');
            $t->unique(['player_id', 'run_id']);
            $t->index(['chapter', 'test_device', 'started_at']);
            $t->index(['player_id', 'started_at']);
        });
        Schema::create('game_chapter_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('player_id');
            $t->string('run_id', 64);
            $t->unsignedInteger('seq');
            $t->string('event', 32);
            $t->string('detail', 96)->default('');
            $t->string('build', 64);
            $t->boolean('test_device');
            $t->dateTime('occurred_at');
            $t->dateTime('received_at');
            $t->longText('state');
            $t->unique(['player_id', 'run_id', 'seq']);
        });
        Schema::create('game_player_logins', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('player_id');
            $t->string('source', 16);
            $t->dateTime('occurred_at');
            $t->index(['player_id', 'occurred_at']);
            $t->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_logins');
        Schema::dropIfExists('game_chapter_events');
        Schema::dropIfExists('game_chapter_runs');
    }
};
