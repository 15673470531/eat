<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 小程序头像上传接口测试（chooseAvatar 流程）
 *
 * 背景：小程序的 chooseAvatar 只能拿到本机临时文件路径，必须上传到服务端才能跨设备显示，
 *      所以登录本身不写头像，头像统一走 /api/user/avatar 上传。
 * 覆盖范围：未登录拒绝、首次上传落库并返回站点相对地址、换头像清理旧文件、非图片拒绝、微信登录不写头像。
 */
class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 造一个普通用户（微信登录后的样子：昵称一样，头像为空）
     */
    private function user(): User
    {
        return User::create([
            'openid' => 'test_avatar_user',
            'name' => '微信用户',
            'nickname' => '微信用户',
            'email' => 'avatar@test.invalid',
            'password' => 'secret',
        ]);
    }

    /**
     * 未登录上传：应返回 401，且不会写入任何文件
     *
     * 预期：挡在鉴权层，避免匿名用户往 storage 里塞垃圾文件
     */
    public function test_avatar_upload_requires_token(): void
    {
        Storage::fake('public');

        $this->postJson('/api/user/avatar', ['file' => UploadedFile::fake()->image('a.png', 100, 100)])->assertStatus(401);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /**
     * 首次上传：文件落到 public 磁盘的 avatars/ 目录，库里只存相对路径
     *
     * 预期：接口下发 /storage/xxx 这种站点相对地址，小程序按自己的 baseUrl 拼接显示，
     *      后端不需要知道自己的公网域名，也不会把 http 地址写进数据库
     */
    public function test_first_upload_stores_file_and_returns_relative_path(): void
    {
        Storage::fake('public');
        $user = $this->user();

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/user/avatar', ['file' => UploadedFile::fake()->image('avatar.png', 120, 120)])
            ->assertOk();

        $path = $user->fresh()->avatar_url;
        $this->assertStringStartsWith('avatars/', (string) $path);
        Storage::disk('public')->assertExists($path);
        $res->assertJsonPath('data.avatarUrl', '/storage/' . $path);
        // 头像随用户资料一起下发，登录态下的页面刷新能拿到
        $this->actingAs($user, 'sanctum')->getJson('/api/user/info')->assertOk()->assertJsonPath('data.avatarUrl', '/storage/' . $path);
    }

    /**
     * 换头像：新文件保留，旧文件被删除
     *
     * 预期：避免用户反复换头像把 storage 撑爆
     */
    public function test_replacing_avatar_removes_old_file(): void
    {
        Storage::fake('public');
        $user = $this->user();

        $this->actingAs($user, 'sanctum')->postJson('/api/user/avatar', ['file' => UploadedFile::fake()->image('first.png', 100, 100)])->assertOk();
        $first = $user->fresh()->avatar_url;

        $this->actingAs($user, 'sanctum')->postJson('/api/user/avatar', ['file' => UploadedFile::fake()->image('second.png', 100, 100)])->assertOk();
        $second = $user->fresh()->avatar_url;

        $this->assertNotEquals($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    /**
     * 非图片文件（伪装成 .jpg 的纯文本）应被拒，头像保持原值
     *
     * 预期：返回 422，且不会误删已有头像文件
     */
    public function test_non_image_is_rejected(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $user->update(['avatar_url' => 'avatars/keep.jpg']);
        Storage::disk('public')->put('avatars/keep.jpg', 'x');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/user/avatar', ['file' => UploadedFile::fake()->create('fake.jpg', 10, 'text/plain')])
            ->assertStatus(422);

        $this->assertEquals('avatars/keep.jpg', $user->fresh()->avatar_url);
        Storage::disk('public')->assertExists('avatars/keep.jpg');
    }

    /**
     * 微信登录本身不写头像：新用户头像为空
     *
     * 预期：小程序端显示默认图标，只有用户主动选了头像才会走上传接口
     */
    public function test_login_keeps_avatar_empty_for_new_user(): void
    {
        config(['wechat.appid' => 'test', 'wechat.secret' => 'secret']);
        Http::fake(['*' => Http::response(['openid' => 'brand_new', 'session_key' => 'hidden'])]);

        $this->postJson('/api/user/login', ['code' => 'valid'])->assertOk()->assertJsonPath('data.avatarUrl', null);
        $this->assertNull(User::where('openid', 'brand_new')->first()->avatar_url);
    }
}
