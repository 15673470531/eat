<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate(['code' => 'required|string|max:256']);
        if (!config('wechat.appid') || !config('wechat.secret')) {
            return response()->json(['code' => 503, 'msg' => '登录服务尚未配置'], 503);
        }
        try {
            $response = Http::connectTimeout(5)->timeout(10)->get('https://api.weixin.qq.com/sns/jscode2session', [
                'appid' => config('wechat.appid'), 'secret' => config('wechat.secret'),
                'js_code' => $validated['code'], 'grant_type' => 'authorization_code',
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['code' => 503, 'msg' => '微信登录暂时不可用，请稍后重试'], 503);
        }
        $body = $response->json();
        if (!$response->successful() || !empty($body['errcode']) || empty($body['openid']) || !is_string($body['openid'])) {
            return response()->json(['code' => 2, 'msg' => '微信凭证已失效，请重新登录'], 422);
        }
        $user = User::firstOrCreate(['openid' => $body['openid']], [
            'name' => '海豚用户', 'nickname' => '海豚用户',
            'email' => hash('sha256', $body['openid']).'@wechat.invalid',
            'password' => Str::random(64),
        ]);
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('wechat-miniprogram', ['*'], now()->addDays(30))->plainTextToken;
        return response()->json(['code' => 0, 'msg' => '登录成功', 'data' => array_merge($this->profile($user), ['token' => $token])]);
    }
    private function profile(User $user): array
    {
        return ['isAdmin' => (int)$user->getRawOriginal('is_admin') === 1, 'userId' => $user->id, 'nickName' => $user->nickname ?: $user->name, 'avatarUrl' => $user->avatar_url];
    }
    public function info(Request $request)
    {
        return response()->json(['code' => 0, 'msg' => 'success', 'data' => $this->profile($request->user())]);
    }
    public function updateName(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:50']);
        $request->user()->update(['name' => $data['name'], 'nickname' => $data['name']]);
        return $this->info($request);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['code' => 0, 'msg' => '退出成功', 'data' => null]);
    }
}
