<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class CatalogStore {
 public function read():array {
  $items=DB::table('ingredients')->orderBy('sort_order')->get()->map(fn($i)=>['id'=>$i->id,'name'=>$i->name,'category'=>$i->category,'aliases'=>json_decode($i->aliases,true),'parent'=>$i->parent,'pantry'=>(bool)$i->pantry,'revision'=>(int)$i->revision]);
  $materials=DB::table('recipe_materials')->orderBy('sort_order')->get()->groupBy('recipe_id');
  $recipes=DB::table('recipes')->orderBy('sort_order')->get()->map(function($r)use($materials){
   $d=array_merge(json_decode($r->details,true),['id'=>$r->id,'name'=>$r->name,'isActive'=>(bool)$r->is_active,'revision'=>(int)$r->revision,'materials'=>['main'=>[],'seasonings'=>[],'optional'=>[]],'required'=>[],'seasonings'=>[],'optional'=>[]]);
   foreach($materials[$r->id]??[] as $m){$row=['id'=>$m->ingredient_id,'amount'=>(float)$m->amount,'unit'=>$m->unit];if($m->note)$row['note']=$m->note;$d['materials'][$m->group_name][]=$row;$d[$m->group_name==='main'?'required':$m->group_name][]=$m->ingredient_id;}
   return $d;
  });
  return ['ingredients'=>['items'=>$items->all(),'categories'=>$items->pluck('category')->unique()->values()->all()],'recipes'=>$recipes->all()];
 }
 public function writeMaterials(string $id,array $materials):void {
  DB::table('recipe_materials')->where('recipe_id',$id)->delete();
  foreach($materials as $group=>$rows)foreach($rows as $i=>$row)DB::table('recipe_materials')->insert(['recipe_id'=>$id,'ingredient_id'=>$row['id'],'group_name'=>$group,'amount'=>$row['amount'],'unit'=>$row['unit'],'note'=>$row['note']??null,'sort_order'=>$i]);
 }
}
