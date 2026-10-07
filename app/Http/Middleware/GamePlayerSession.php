<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GamePlayerSession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! $token || ! preg_match('/^gp_[a-f0-9]{64}$/', $token)) {
            return response()->json(['message' => '请先微信登录'], 401);
        }
        $session = DB::table('game_player_sessions')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        if (! $session) {
            return response()->json(['message' => '登录已过期，请重新登录'], 401);
        }
        $player = DB::table('game_players')->where('id', $session->player_id)->where('appid', config('game_account.appid'))->first();
        if (! $player) {
            return response()->json(['message' => '登录账号不可用'], 401);
        }
        $request->attributes->set('game_player', $player);

        return $next($request);
    }
}
