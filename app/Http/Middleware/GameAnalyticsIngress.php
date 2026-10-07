<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GameAnalyticsIngress
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('game_analytics.enabled')) {
            return response()->json(['code' => 503, 'msg' => '统计上报暂时关闭'], 503);
        }
        if (! $request->isJson()) {
            return response()->json(['code' => 415, 'msg' => '请使用 application/json'], 415);
        }
        if (strlen($request->getContent()) > 65536) {
            return response()->json(['code' => 413, 'msg' => '单次请求不能超过 64 KiB'], 413);
        }

        return $next($request);
    }
}
