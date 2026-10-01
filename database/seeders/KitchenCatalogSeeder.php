<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class KitchenCatalogSeeder extends Seeder {
 public function run(): void {
  $catalog=json_decode(file_get_contents(database_path('catalog.json')),true,512,JSON_THROW_ON_ERROR);
  DB::transaction(function() use($catalog){
   DB::table('app_settings')->insertOrIgnore(['key'=>'about','value'=>json_encode(['name'=>'海豚带你做饭','slogan'=>'有啥做啥，好好吃饭。','email'=>'1174430282@qq.com','wechat'=>'mistyMystery928'],JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);
   foreach($catalog['ingredients']['items'] as $i=>$item) DB::table('ingredients')->updateOrInsert(['id'=>$item['id']],['name'=>$item['name'],'category'=>$item['category'],'aliases'=>json_encode($item['aliases'],JSON_UNESCAPED_UNICODE),'parent'=>$item['parent']??null,'pantry'=>$item['pantry']??false,'sort_order'=>$i,'created_at'=>now(),'updated_at'=>now()]);
   foreach($catalog['recipes'] as $i=>$recipe){
    $details=$recipe;unset($details['id'],$details['name'],$details['materials'],$details['required'],$details['seasonings'],$details['optional']);
    DB::table('recipes')->updateOrInsert(['id'=>$recipe['id']],['name'=>$recipe['name'],'details'=>json_encode($details,JSON_UNESCAPED_UNICODE),'sort_order'=>$i,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('recipe_materials')->where('recipe_id',$recipe['id'])->delete();
    foreach($recipe['materials'] as $group=>$rows)foreach($rows as $j=>$row)DB::table('recipe_materials')->insert(['recipe_id'=>$recipe['id'],'ingredient_id'=>$row['id'],'group_name'=>$group,'amount'=>$row['amount'],'unit'=>$row['unit'],'note'=>$row['note']??null,'sort_order'=>$j]);
   }
  });
 }
}
