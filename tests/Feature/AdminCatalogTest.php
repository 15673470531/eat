<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class AdminCatalogTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();$this->seed(\Database\Seeders\KitchenCatalogSeeder::class);}
 private function login(bool $admin):User {$u=User::create(['name'=>'Tester','email'=>uniqid().'@test.invalid','password'=>'pass','is_admin'=>$admin]);$this->actingAs($u,'sanctum');return $u;}
 private function recipe():array {return json_decode(file_get_contents(database_path('catalog.json')),true)['recipes'][0];}
 public function test_roles_and_profile():void {
  $this->getJson('/api/admin/catalog')->assertStatus(401);
  $u=$this->login(false);$this->getJson('/api/user/info')->assertJsonPath('data.isAdmin',false);
  foreach(['/api/admin/ingredients','/api/admin/recipes'] as $path)$this->postJson($path,[])->assertStatus(403);
  $this->putJson('/api/admin/recipes/dish_qingjiao_chaodan',[])->assertStatus(403);
  $this->patchJson('/api/admin/recipes/dish_qingjiao_chaodan/status',[])->assertStatus(403);
  $this->getJson('/api/admin/catalog')->assertStatus(403);
  $u->update(['is_admin'=>true]);$this->getJson('/api/user/info')->assertJsonPath('data.isAdmin',true);$this->getJson('/api/admin/catalog')->assertOk();
  $u->update(['is_admin'=>false]);$this->getJson('/api/admin/catalog')->assertStatus(403);
 }
 public function test_ingredient_create_edit_duplicate_and_stale_revision():void {
  $this->login(true);$item=['name'=>'测试秋葵','category'=>'蔬菜','aliases'=>['测试羊角豆']];
  $id=$this->postJson('/api/admin/ingredients',$item)->assertOk()->json('data.id');
  $this->postJson('/api/admin/ingredients',$item)->assertStatus(422);
  $item['revision']=1;$item['name']='新测试秋葵';$this->putJson('/api/admin/ingredients/'.$id,$item)->assertOk();
  $this->putJson('/api/admin/ingredients/'.$id,$item)->assertStatus(409);
  $this->assertDatabaseHas('ingredients',['id'=>$id,'name'=>'新测试秋葵','revision'=>2]);
 }
 public function test_recipe_edit_copy_status_and_seed_preserves_edits():void {
  $this->login(true);$r=$this->recipe();$r['revision']=1;$r['tip']='管理员修改的小贴士';
  $this->putJson('/api/admin/recipes/'.$r['id'],$r)->assertOk();
  $this->putJson('/api/admin/recipes/'.$r['id'],$r)->assertStatus(409);
  $this->seed(\Database\Seeders\KitchenCatalogSeeder::class);
  $data=$this->getJson('/api/catalog')->json('data.recipes');$found=collect($data)->firstWhere('id',$r['id']);$this->assertSame($r['tip'],$found['tip']);
  $this->patchJson('/api/admin/recipes/'.$r['id'].'/status',['revision'=>2,'isActive'=>false])->assertOk();
  $found=collect($this->getJson('/api/catalog')->json('data.recipes'))->firstWhere('id',$r['id']);$this->assertFalse($found['isActive']);
  $r['name']='青椒炒蛋测试副本';$new=$this->postJson('/api/admin/recipes',$r)->assertOk()->json('data.id');$this->assertNotEquals($r['id'],$new);
  $this->assertDatabaseCount('recipes',45);
  $this->patchJson('/api/admin/recipes/'.$r['id'].'/status',['revision'=>3,'isActive'=>true])->assertOk();
 }
 public function test_invalid_recipe_cannot_partially_replace_materials():void {
  $this->login(true);$r=$this->recipe();$r['revision']=1;
  $before=DB::table('recipe_materials')->where('recipe_id',$r['id'])->count();
  $r['materials']['main'][0]['amount']=0;$this->putJson('/api/admin/recipes/'.$r['id'],$r)->assertStatus(422);
  $r=$this->recipe();$r['revision']=1;$r['materials']['optional'][0]['note']='';$this->putJson('/api/admin/recipes/'.$r['id'],$r)->assertStatus(422);
  $r=$this->recipe();$r['revision']=1;$r['materials']['main'][]=$r['materials']['main'][0];$this->putJson('/api/admin/recipes/'.$r['id'],$r)->assertStatus(422);
  $this->assertSame($before,DB::table('recipe_materials')->where('recipe_id',$r['id'])->count());$this->assertDatabaseHas('recipes',['id'=>$r['id'],'revision'=>1]);
 }
}
