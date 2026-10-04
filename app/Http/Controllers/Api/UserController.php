<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
            'name' => '微信用户', 'nickname' => '微信用户',
            'email' => hash('sha256', $body['openid']).'@wechat.invalid',
            'password' => Str::random(64),
        ]);
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('wechat-miniprogram', ['*'], now()->addDays(30))->plainTextToken;
        return response()->json(['code' => 0, 'msg' => '登录成功', 'data' => array_merge($this->profile($user), ['token' => $token])]);
    }
    private function profile(User $user): array
    {
        return ['isAdmin' => (int)$user->getRawOriginal('is_admin') === 1, 'userId' => $user->id, 'nickName' => $user->nickname ?: $user->name, 'avatarUrl' => $this->avatarPath($user)];
    }

    /**
     * 头像统一以站点相对路径（/storage/xxx）下发。
     * 小程序按自己的 baseUrl 拼成完整地址，这样开发机（127.0.0.1 / 局域网 IP）和生产域名都不用改后端配置。
     */
    private function avatarPath(User $user): ?string
    {
        $path = $user->avatar_url;
        if (empty($path)) {
            return null;
        }
        // 兼容历史数据里可能存过的完整地址
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return '/storage/' . ltrim($path, '/');
    }

    /**
     * POST /api/user/avatar
     * 更换头像：接收小程序 chooseAvatar 选出的图片（临时文件），存入 public 磁盘，返回最新资料
     */
    public function avatar(Request $request)
    {
        $request->validate(['file' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048'], [
            'file.required' => '没有收到头像文件，请重新选择',
            'file.image' => '头像必须是图片',
            'file.mimes' => '头像仅支持 jpg / png / webp 格式',
            'file.max' => '头像不能超过 2MB',
        ]);

        $user = $request->user();
        $old = $user->avatar_url;
        $path = $request->file('file')->store('avatars', 'public');

        if (empty($path)) {
            return response()->json(['code' => 1, 'msg' => '头像保存失败，请重试'], 500);
        }

        $user->update(['avatar_url' => $path]);

        // 旧头像及时清理，只删自己上传目录里的文件，不碰外链地址
        if (!empty($old) && $old != $path && !preg_match('#^https?://#i', $old) && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['code' => 0, 'msg' => '头像已更新', 'data' => $this->profile($user->refresh())]);
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
