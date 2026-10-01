<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\CatalogStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class AdminCatalogController extends Controller {
 private function ok($data){return response()->json(['code'=>0,'msg'=>'success','data'=>$data]);}
 private function conflict(){return response()->json(['code'=>409,'msg'=>'内容已被更新，请返回列表重新打开后再编辑'],409);}
 public function index(CatalogStore $store){return $this->ok($store->read());}
 public function ingredient(Request $request,?string $id=null){
  $data=$request->validate([
   'name'=>['required','string','max:50',Rule::unique('ingredients','name')->ignore($id,'id')],
   'category'=>'required|in:蔬菜,菌菇,豆制品,肉禽,蛋奶,水产,主食,干货,加工肉,其他,调料',
   'aliases'=>'present|array|max:20','aliases.*'=>'required|string|max:50|distinct','revision'=>$id?'required|integer|min:1':'sometimes|integer|min:1',
  ], ['name.unique'=>'这个名称已存在，请直接编辑已有记录。','materials.optional.*.note.required'=>'请说明可选材料省略后的区别。']);
  return DB::transaction(function()use($data,$id){
   if($id){$row=DB::table('ingredients')->where('id',$id)->lockForUpdate()->first();abort_unless($row,404);if((int)$row->revision!==(int)$data['revision'])return $this->conflict();}
   $newId=$id??'ing_'.Str::uuid();
   $values=['name'=>$data['name'],'category'=>$data['category'],'aliases'=>json_encode($data['aliases'],JSON_UNESCAPED_UNICODE),'updated_at'=>now()];
   if($id){$values['revision']=$row->revision+1;DB::table('ingredients')->where('id',$id)->update($values);}
   else DB::table('ingredients')->insert(array_merge($values,['id'=>$newId,'parent'=>null,'pantry'=>false,'sort_order'=>(DB::table('ingredients')->max('sort_order')??0)+1,'created_at'=>now(),'revision'=>1]));
   return $this->ok(['id'=>$newId]);
  });
 }
 public function recipe(Request $request,CatalogStore $store,?string $id=null){
  $data=$request->validate([
   'name'=>['required','string','max:80',Rule::unique('recipes','name')->ignore($id,'id')],
   'description'=>'nullable|string|max:300','emoji'=>'nullable|string|max:16',
   'servings'=>'required|integer|between:1,8','timeMin'=>'required|integer|between:1,480','difficulty'=>'required|integer|between:1,3',
   'equipment'=>'required|in:炒锅,煮锅,蒸锅,免开火','spicy'=>'required|boolean',
   'categories'=>'required|array|min:1|max:6','categories.*'=>'required|distinct|in:stirfry,soup,braise,steam,cold,staple',
   'tags'=>'present|array|max:10','tags.*'=>'required|string|max:20|distinct',
   'steps'=>'required|array|min:1|max:30','steps.*'=>'required|string|max:1000',
   'tip'=>'nullable|string|max:1000','portionNote'=>'nullable|string|max:300',
   'materials'=>'required|array:main,seasonings,optional',
   'materials.main'=>'required|array|min:1|max:30','materials.seasonings'=>'present|array|max:30','materials.optional'=>'present|array|max:30',
   'materials.*.*'=>'required|array:id,amount,unit,note','materials.*.*.id'=>'required|string|exists:ingredients,id',
   'materials.*.*.amount'=>'required|numeric|gt:0|max:99999|decimal:0,2',
   'materials.*.*.unit'=>'required|in:个,根,盒,克,袋,瓶,把,份,毫升,瓣,粒,汤匙,茶匙,块,片,碗',
   'materials.*.*.note'=>'nullable|string|max:500','materials.optional.*.note'=>'required|string|max:500',
   'revision'=>$id?'required|integer|min:1':'sometimes|integer|min:1',
  ], ['name.unique'=>'这个名称已存在，请直接编辑已有记录。','materials.optional.*.note.required'=>'请说明可选材料省略后的区别。']);
  $ids=collect($data['materials'])->flatten(1)->pluck('id');
  if($ids->unique()->count()!==$ids->count())throw ValidationException::withMessages(['materials'=>'同一种食材只能出现一次，请合并用量。']);
  return DB::transaction(function()use($data,$id,$store){
   if($id){$row=DB::table('recipes')->where('id',$id)->lockForUpdate()->first();abort_unless($row,404);if((int)$row->revision!==(int)$data['revision'])return $this->conflict();}
   $newId=$id??'dish_'.Str::uuid();$details=$data;unset($details['name'],$details['materials'],$details['revision']);
   $values=['name'=>$data['name'],'details'=>json_encode($details,JSON_UNESCAPED_UNICODE),'updated_at'=>now()];
   if($id){$values['revision']=$row->revision+1;DB::table('recipes')->where('id',$id)->update($values);}
   else DB::table('recipes')->insert(array_merge($values,['id'=>$newId,'is_active'=>true,'revision'=>1,'sort_order'=>(DB::table('recipes')->max('sort_order')??0)+1,'created_at'=>now()]));
   $store->writeMaterials($newId,$data['materials']);return $this->ok(['id'=>$newId]);
  });
 }
 public function status(Request $request,string $id){
  $data=$request->validate(['revision'=>'required|integer|min:1','isActive'=>'required|boolean']);
  return DB::transaction(function()use($data,$id){
   $row=DB::table('recipes')->where('id',$id)->lockForUpdate()->first();abort_unless($row,404);
   if((int)$row->revision!==(int)$data['revision'])return $this->conflict();
   DB::table('recipes')->where('id',$id)->update(['is_active'=>$data['isActive'],'revision'=>$row->revision+1,'updated_at'=>now()]);return $this->ok(['id'=>$id]);
  });
 }
}
