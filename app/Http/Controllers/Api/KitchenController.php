<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\KitchenStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class KitchenController extends Controller {
 public function show(Request $request,KitchenStore $store){
  $id=$request->user()->id;
  $data=DB::transaction(function()use($store,$id){$store->ensure($id);DB::table('user_kitchens')->where('user_id',$id)->lockForUpdate()->first();return $store->read($id);});
  return response()->json(['code'=>0,'msg'=>'success','data'=>$data]);
 }
 public function update(Request $request,KitchenStore $store){
  $data=$request->validate([
   'revision'=>'required|integer|min:0','requestId'=>'required|uuid',
   'state'=>'required|array:selected,quick,mild,equipment,servings,homeMode,recipeSource,inventory,favorites,shopping',
   'state.selected'=>'present|array|max:300','state.selected.*'=>'required|string|distinct|exists:ingredients,id',
   'state.quick'=>'required|boolean','state.mild'=>'required|boolean','state.servings'=>'required|integer|between:1,4',
   'state.equipment'=>'required|in:全部,炒锅,煮锅,蒸锅,免开火','state.homeMode'=>'required|in:fridge,selected','state.recipeSource'=>'required|in:fridge,selected',
   'state.inventory'=>'present|array|max:1000','state.inventory.*'=>'required|array:key,id,quantity,unit,location',
   'state.inventory.*.key'=>'required|string|max:100|distinct','state.inventory.*.id'=>'required|string|exists:ingredients,id',
   'state.inventory.*.quantity'=>'present|nullable|numeric|gt:0|max:999999|decimal:0,2',
   'state.inventory.*.unit'=>'required|in:个,根,盒,克,袋,瓶,把,份,毫升','state.inventory.*.location'=>'required|in:冷藏,冷冻,冰箱外',
   'state.favorites'=>'present|array|max:1000','state.favorites.*'=>'required|string|distinct|exists:recipes,id',
   'state.shopping'=>'present|array|max:300','state.shopping.*'=>'required|array:id,checked,sources',
   'state.shopping.*.id'=>'required|string|distinct|exists:ingredients,id','state.shopping.*.checked'=>'required|boolean',
   'state.shopping.*.sources'=>'present|array|max:100','state.shopping.*.sources.*'=>'required|string|max:100',
  ]);
  $result=$store->write($request->user()->id,$data);
  return response()->json(['code'=>$result['conflict']?409:0,'msg'=>$result['conflict']?'其他设备已修改，请选择保留哪份数据':'success','data'=>$result['data']],$result['conflict']?409:200);
 }
}
