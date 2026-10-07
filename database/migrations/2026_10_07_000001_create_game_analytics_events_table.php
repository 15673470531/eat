<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->char('event_key', 64)->unique();
            $table->string('game_id', 32);
            $table->uuid('player_id');
            $table->string('run_id', 64);
            $table->unsignedInteger('seq');
            $table->string('event_name', 32);
            $table->string('build', 64);
            $table->unsignedSmallInteger('stage');
            $table->unsignedSmallInteger('wave');
            $table->string('weapon', 32);
            $table->unsignedInteger('seconds');
            $table->string('detail', 64)->default('');
            $table->boolean('test_device')->default(false);
            $table->string('previous_run_id', 64)->default('');
            $table->dateTime('occurred_at');
            $table->dateTime('received_at');
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
