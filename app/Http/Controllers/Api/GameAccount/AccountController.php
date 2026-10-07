<?php

namespace App\Http\Controllers\Api\GameAccount;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AccountController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:256', 'client_appid' => 'sometimes|string|max:64']);
        $appid = config('game_account.appid');
        $secret = config('game_account.secret');
        if (! $appid || ! $secret) {
            return response()->json(['message' => '小游戏微信登录尚未配置'], 503);
        }
        if (isset($data['client_appid']) && $data['client_appid'] !== $appid) {
            return response()->json(['message' => '客户端与服务器的小游戏 AppID 不一致，请检查 GAME_WECHAT_APPID'], 503);
        }
        try {
            $res = Http::connectTimeout(5)->timeout(10)->get('https://api.weixin.qq.com/sns/jscode2session', [
                'appid' => $appid, 'secret' => $secret, 'js_code' => $data['code'], 'grant_type' => 'authorization_code']);
        } catch (ConnectionException $e) {
            return response()->json(['message' => '微信登录暂时不可用，请重试'], 503);
        }
        $body = $res->json();
        if (! $res->successful() || ! is_array($body) || ! empty($body['errcode']) || ! is_string($body['openid'] ?? null) || strlen($body['openid']) > 128 || $body['openid'] === '') {
            // Return only the numeric code: never expose secret, login code or session_key.
            $error = is_array($body) && is_numeric($body['errcode'] ?? null) ? (int) $body['errcode'] : null;

            return response()->json(['message' => $error !== null ? '微信登录校验未通过（错误码 '.$error.'），请重试或检查小游戏配置' : '微信登录服务响应异常，请稍后重试'], 422);
        }
        // Upsert handles simultaneous first logins without creating duplicate accounts.
        DB::table('game_players')->upsert([['appid' => $appid, 'openid' => $body['openid'], 'tutorial_completed' => false, 'created_at' => now(), 'updated_at' => now(), 'last_login_at' => now()]], ['appid', 'openid'], ['last_login_at', 'updated_at']);
        $player = DB::table('game_players')->where('appid', $appid)->where('openid', $body['openid'])->first();
        $token = 'gp_'.bin2hex(random_bytes(32));
        DB::table('game_player_sessions')->where('player_id', $player->id)->where('expires_at', '<=', now())->delete();
        DB::table('game_player_sessions')->insert(['token_hash' => hash('sha256', $token), 'player_id' => $player->id, 'expires_at' => now()->addDays(30)]);

        return response()->json(['data' => ['token' => $token, 'player_id' => $player->id, 'tutorial_completed' => (bool) $player->tutorial_completed, 'save' => $this->save($player->id)]]);
    }

    public function show(Request $request)
    {
        $p = $request->attributes->get('game_player');

        return response()->json(['data' => ['player_id' => $p->id, 'tutorial_completed' => (bool) $p->tutorial_completed, 'save' => $this->save($p->id)]]);
    }

    private function save(int $id): ?array
    {
        $s = DB::table('game_player_saves')->where('player_id', $id)->first();

        return $s ? ['revision' => $s->revision, 'payload' => json_decode($s->payload, true), 'updated_at' => $s->updated_at] : null;
    }
}
