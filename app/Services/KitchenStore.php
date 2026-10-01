<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class KitchenStore {
 public static function defaults(): array {return ['selected'=>[],'quick'=>false,'mild'=>false,'equipment'=>'全部','servings'=>2,'homeMode'=>'fridge','recipeSource'=>'selected'];}
 public function ensure(int $id): void {DB::table('user_kitchens')->insertOrIgnore(['user_id'=>$id,'preferences'=>json_encode(self::defaults()),'created_at'=>now(),'updated_at'=>now()]);}
 public function read(int $id): array {
  $k=DB::table('user_kitchens')->where('user_id',$id)->first();
  $state=json_decode($k->preferences,true);
  $state['inventory']=DB::table('user_inventory')->where('user_id',$id)->orderBy('sort_order')->get()->map(fn($r)=>['key'=>$r->entry_key,'id'=>$r->ingredient_id,'quantity'=>$r->quantity===null?null:(float)$r->quantity,'unit'=>$r->unit,'location'=>$r->location])->all();
  $state['favorites']=DB::table('user_favorites')->where('user_id',$id)->orderBy('sort_order')->pluck('recipe_id')->all();
  $state['shopping']=DB::table('user_shopping_items')->where('user_id',$id)->orderBy('sort_order')->get()->map(fn($r)=>['id'=>$r->ingredient_id,'checked'=>(bool)$r->checked,'sources'=>json_decode($r->sources,true)])->all();
  return ['revision'=>(int)$k->revision,'state'=>$state];
 }
 public function write(int $id,array $data): array {
  return DB::transaction(function()use($id,$data){
   $this->ensure($id);
   $current=DB::table('user_kitchens')->where('user_id',$id)->lockForUpdate()->first();
   $hash=hash('sha256',json_encode($data['state']));
   if($current->last_request_id===$data['requestId'] && $current->last_request_hash===$hash)return ['conflict'=>false,'data'=>$this->read($id)];
   if((int)$current->revision!==$data['revision'])return ['conflict'=>true,'data'=>$this->read($id)];
   $state=$data['state'];
   foreach(['user_inventory','user_favorites','user_shopping_items'] as $table)DB::table($table)->where('user_id',$id)->delete();
   foreach($state['inventory'] as $i=>$r)DB::table('user_inventory')->insert(['user_id'=>$id,'entry_key'=>$r['key'],'ingredient_id'=>$r['id'],'quantity'=>$r['quantity'],'unit'=>$r['unit'],'location'=>$r['location'],'sort_order'=>$i]);
   foreach($state['favorites'] as $i=>$recipe)DB::table('user_favorites')->insert(['user_id'=>$id,'recipe_id'=>$recipe,'sort_order'=>$i]);
   foreach($state['shopping'] as $i=>$r)DB::table('user_shopping_items')->insert(['user_id'=>$id,'ingredient_id'=>$r['id'],'checked'=>$r['checked'],'sources'=>json_encode($r['sources'],JSON_UNESCAPED_UNICODE),'sort_order'=>$i]);
   $preferences=array_intersect_key($state,self::defaults());
   DB::table('user_kitchens')->where('user_id',$id)->update(['preferences'=>json_encode($preferences,JSON_UNESCAPED_UNICODE),'revision'=>$current->revision+1,'last_request_id'=>$data['requestId'],'last_request_hash'=>$hash,'updated_at'=>now()]);
   return ['conflict'=>false,'data'=>$this->read($id)];
  });
 }
}
