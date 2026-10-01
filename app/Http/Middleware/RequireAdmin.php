<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RequireAdmin {
 public function handle(Request $request,Closure $next){
  if (!$request->user() || (int)$request->user()->getRawOriginal('is_admin')!==1) return response()->json(['code'=>403,'msg'=>'仅管理员可管理菜谱'],403);
  return $next($request);
 }
}
