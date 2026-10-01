<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
class CatalogController extends Controller {
 public function about(){return response()->json(['code'=>0,'msg'=>'success','data'=>json_decode(DB::table('app_settings')->where('key','about')->value('value')??'{}',true)]);}
 public function index(){
  $items=DB::table('ingredients')->orderBy('sort_order')->get()->map(fn($i)=>['id'=>$i->id,'name'=>$i->name,'category'=>$i->category,'aliases'=>json_decode($i->aliases,true),'parent'=>$i->parent,'pantry'=>(bool)$i->pantry]);
  $materials=DB::table('recipe_materials')->orderBy('sort_order')->get()->groupBy('recipe_id');
  $recipes=DB::table('recipes')->orderBy('sort_order')->get()->map(function($r)use($materials){
   $d=array_merge(json_decode($r->details,true),['id'=>$r->id,'name'=>$r->name,'materials'=>['main'=>[],'seasonings'=>[],'optional'=>[]],'required'=>[],'seasonings'=>[],'optional'=>[]]);
   foreach($materials[$r->id]??[] as $m){$row=['id'=>$m->ingredient_id,'amount'=>(float)$m->amount,'unit'=>$m->unit];if($m->note)$row['note']=$m->note;$d['materials'][$m->group_name][]=$row;$d[$m->group_name==='main'?'required':$m->group_name][]=$m->ingredient_id;}
   return $d;
  });
  return response()->json(['code'=>0,'msg'=>'success','data'=>['ingredients'=>['items'=>$items,'categories'=>$items->pluck('category')->unique()->values()],'recipes'=>$recipes]]);
 }
}
