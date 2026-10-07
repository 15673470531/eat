<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GameAnalyticsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || (int) $request->user()->getRawOriginal('is_admin') !== 1) {
            return response()->json(['code' => 403, 'msg' => '仅管理员可查看游戏统计'], 403);
        }

        return $next($request);
    }
}
