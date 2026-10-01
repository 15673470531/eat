<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\CatalogStore;
use Illuminate\Support\Facades\DB;
class CatalogController extends Controller {
 public function about(){return response()->json(['code'=>0,'msg'=>'success','data'=>json_decode(DB::table('app_settings')->where('key','about')->value('value')??'{}',true)]);}
 public function index(CatalogStore $store){return response()->json(['code'=>0,'msg'=>'success','data'=>$store->read()]);}
}
