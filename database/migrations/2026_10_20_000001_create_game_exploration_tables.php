<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('game_exploration_runs', function(Blueprint $t){$t->id();$t->uuid('player_id');$t->string('run_id',64);$t->string('build',64)->index();$t->boolean('test_device');$t->unsignedInteger('last_seq');$t->string('result',16)->index();$t->unsignedTinyInteger('sword_level');$t->dateTime('started_at')->index();$t->dateTime('occurred_at');$t->longText('record');$t->unique(['player_id','run_id']);});
  Schema::create('game_exploration_events', function(Blueprint $t){$t->id();$t->uuid('player_id');$t->string('run_id',64);$t->unsignedInteger('seq');$t->string('event',32);$t->dateTime('occurred_at');$t->text('data');$t->unique(['player_id','run_id','seq']);});
 }
 public function down(): void {Schema::dropIfExists('game_exploration_events');Schema::dropIfExists('game_exploration_runs');}
};
