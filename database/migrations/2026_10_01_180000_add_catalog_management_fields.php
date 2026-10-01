<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('recipes',function(Blueprint $t){$t->boolean('is_active')->default(true);$t->unsignedInteger('revision')->default(1);$t->unique('name');});
  Schema::table('ingredients',function(Blueprint $t){$t->unsignedInteger('revision')->default(1);$t->unique('name');});
 }
 public function down():void {
  Schema::table('recipes',function(Blueprint $t){$t->dropUnique(['name']);$t->dropColumn(['is_active','revision']);});
  Schema::table('ingredients',function(Blueprint $t){$t->dropUnique(['name']);$t->dropColumn('revision');});
 }
};
