<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
// 「关于我们」的内容（小程序 pages/about 页）。
// DEFAULTS 是打底值：数据库 app_settings.about 里写了同名字段就覆盖它，
// 这样新增字段（如公众号文章链接）不用等小程序发版，线上没写过库也能立即生效。
class AboutSettings {
 const KEY = 'about';
 const DEFAULTS = [
  'name' => '吃啥不愁了',
  'slogan' => '有啥做啥，好好吃饭。',
  // 公众号文章地址：小程序「关于我们」页点公众号打开的就是它
  'article' => 'https://mp.weixin.qq.com/s/nFlGkyzRZNiJNd7ZOdrqVA',
  // 公众号那一行的显示文案
  'articleText' => '领取福利',
 ];
 // 允许修改的字段名
 public static function fields(): array { return array_keys(self::DEFAULTS); }
 // 库里存的字段覆盖默认值；库里没写或写空的字段仍用默认值
 public static function read(): array {
  return array_merge(self::DEFAULTS, self::stored());
 }
 // 库里那一行 JSON，只保留非空字符串字段，避免脏数据覆盖默认值
 private static function stored(): array {
  $stored = json_decode(self::raw() ?? '{}', true);
  return is_array($stored) ? array_filter($stored, fn ($value) => is_string($value) && $value != '') : [];
 }
 private static function raw(): ?string {
  $value = DB::table('app_settings')->where('key', self::KEY)->value('value');
  return $value === null ? null : (string) $value;
 }
 // 空数组编码成 {} 而不是 []，保证这一行永远是 JSON 对象
 private static function encode(array $stored): string {
  return $stored ? json_encode($stored, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '{}';
 }
 // 改一个字段：读改写一行 JSON，重复执行不会产生重复记录
 public static function put(string $field, string $value): array {
  if (!in_array($field, self::fields(), true)) throw new InvalidArgumentException('不支持的字段：'.$field);
  $stored = self::stored(); $stored[$field] = $value;
  self::save(self::encode($stored));
  return self::read();
 }
 // 删掉某个字段，退回默认值
 public static function forget(string $field): array {
  if (!in_array($field, self::fields(), true)) throw new InvalidArgumentException('不支持的字段：'.$field);
  $stored = self::stored(); unset($stored[$field]);
  self::save(self::encode($stored));
  return self::read();
 }
 private static function save(string $json): void {
  if (self::raw() === null) DB::table('app_settings')->insert(['key' => self::KEY, 'value' => $json, 'created_at' => now(), 'updated_at' => now()]);
  else DB::table('app_settings')->where('key', self::KEY)->update(['value' => $json, 'updated_at' => now()]);
 }
}
