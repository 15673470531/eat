<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Services\AboutSettings;

/**
 * 「关于我们」接口（/api/about）与 about:set 命令的测试
 *
 * 背景：关于我们页的内容由后端下发，公众号文章链接要能不发小程序版本就换掉。
 * 覆盖：默认值打底、数据库字段覆盖默认、命令行改单个字段、非法字段被拒绝。
 */
class AboutSettingsTest extends TestCase {
 use RefreshDatabase;

 /**
 * 库里还没写过 app_settings 记录时，接口也要返回完整字段（含公众号文章）
 *
 * 预期：article / articleText 取代码默认值，小程序关于我们页不会出现空行
 */
 public function test_about_endpoint_falls_back_to_defaults():void {
  $this->assertSame(0,DB::table('app_settings')->where('key','about')->count());
  $data=$this->getJson('/api/about')->assertOk()->json('data');
  $this->assertSame(AboutSettings::DEFAULTS['article'],$data['article']);
  $this->assertSame('领取福利',$data['articleText']);
  $this->assertSame('吃啥不愁了',$data['name']);
 }

 /**
 * 数据库里写了同名字段时以数据库为准，没写的字段继续用默认值
 *
 * 预期：改过的 article 生效，其他字段不受影响
 */
 public function test_database_overrides_defaults_field_by_field():void {
  AboutSettings::put('article','https://mp.weixin.qq.com/s/changed');
  $data=$this->getJson('/api/about')->assertOk()->json('data');
  $this->assertSame('https://mp.weixin.qq.com/s/changed',$data['article']);
  $this->assertSame('领取福利',$data['articleText']);
  $this->assertSame('吃啥不愁了',$data['name']);
 }

 /**
 * about:set 改链接：只动一个字段，重复执行不会写出重复记录
 *
 * 预期：两次执行后 app_settings 里仍只有一行，两个字段都在
 */
 public function test_about_set_command_updates_single_field():void {
  $this->assertSame(0,Artisan::call('about:set',['field'=>'article','value'=>'https://mp.weixin.qq.com/s/cli']));
  $this->assertSame(0,Artisan::call('about:set',['field'=>'name','value'=>'吃啥厨房']));
  $this->assertSame(1,DB::table('app_settings')->where('key','about')->count());
  $stored=json_decode(DB::table('app_settings')->where('key','about')->value('value'),true);
  $this->assertSame('https://mp.weixin.qq.com/s/cli',$stored['article']);
  $this->assertSame('吃啥厨房',$stored['name']);
 }

 /**
 * --forget 删掉字段后接口退回默认值，库里那行仍是 JSON 对象（不写成 []）
 *
 * 预期：article 回到代码默认链接，value 为 {}
 */
 public function test_forget_restores_default():void {
  AboutSettings::put('article','https://mp.weixin.qq.com/s/cli');
  Artisan::call('about:set',['field'=>'article','--forget'=>true]);
  $this->assertSame(AboutSettings::DEFAULTS['article'],$this->getJson('/api/about')->json('data.article'));
  $this->assertSame('{}',DB::table('app_settings')->where('key','about')->value('value'));
 }

 /**
 * 不支持的字段直接失败，不写库（避免脏数据覆盖默认值）
 *
 * 预期：退出码 1，app_settings 仍是空
 */
 public function test_about_set_rejects_unknown_field():void {
  $this->assertSame(1,Artisan::call('about:set',['field'=>'unknown','value'=>'x']));
  $this->assertSame(0,DB::table('app_settings')->where('key','about')->count());
 }

 /**
 * 不带参数时只列出当前内容，不改库
 */
 public function test_about_set_without_arguments_prints_current():void {
  $this->assertSame(0,Artisan::call('about:set'));
  $this->assertSame(0,DB::table('app_settings')->where('key','about')->count());
 }
}
