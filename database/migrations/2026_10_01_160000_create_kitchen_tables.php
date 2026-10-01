<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('app_settings',function(Blueprint $t){$t->string('key',64)->primary();$t->json('value');$t->timestamps();});
  Schema::create('ingredients', function(Blueprint $t){$t->string('id',64)->primary();$t->string('name');$t->string('category',32);$t->json('aliases');$t->string('parent',64)->nullable();$t->boolean('pantry')->default(false);$t->unsignedInteger('sort_order');$t->timestamps();});
  Schema::create('recipes', function(Blueprint $t){$t->string('id',64)->primary();$t->string('name');$t->json('details');$t->unsignedInteger('sort_order');$t->timestamps();});
  Schema::create('recipe_materials',function(Blueprint $t){$t->id();$t->string('recipe_id',64);$t->string('ingredient_id',64);$t->string('group_name',16);$t->decimal('amount',10,2);$t->string('unit',16);$t->text('note')->nullable();$t->unsignedInteger('sort_order');$t->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();$t->foreign('ingredient_id')->references('id')->on('ingredients');$t->unique(['recipe_id','ingredient_id']);});
  Schema::create('user_kitchens',function(Blueprint $t){$t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();$t->unsignedInteger('revision')->default(0);$t->json('preferences');$t->uuid('last_request_id')->nullable();$t->string('last_request_hash',64)->nullable();$t->timestamps();});
  Schema::create('user_inventory',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('entry_key',100);$t->string('ingredient_id',64);$t->decimal('quantity',10,2)->nullable();$t->string('unit',16);$t->string('location',16);$t->unsignedInteger('sort_order');$t->foreign('ingredient_id')->references('id')->on('ingredients');$t->unique(['user_id','entry_key']);});
  Schema::create('user_favorites',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('recipe_id',64);$t->unsignedInteger('sort_order');$t->foreign('recipe_id')->references('id')->on('recipes');$t->unique(['user_id','recipe_id']);});
  Schema::create('user_shopping_items',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('ingredient_id',64);$t->boolean('checked');$t->json('sources');$t->unsignedInteger('sort_order');$t->foreign('ingredient_id')->references('id')->on('ingredients');$t->unique(['user_id','ingredient_id']);});
 }
 public function down(): void {foreach(['user_shopping_items','user_favorites','user_inventory','user_kitchens','recipe_materials','recipes','ingredients','app_settings'] as $name)Schema::dropIfExists($name);}
};
