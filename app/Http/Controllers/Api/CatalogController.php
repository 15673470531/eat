<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\AboutSettings;
use App\Services\CatalogStore;
class CatalogController extends Controller {
 // 关于我们内容：AboutSettings 里代码默认值打底，app_settings.about 里的字段覆盖它
 public function about(){return response()->json(['code'=>0,'msg'=>'success','data'=>AboutSettings::read()]);}
 public function index(CatalogStore $store){return response()->json(['code'=>0,'msg'=>'success','data'=>$store->read()]);}
}
