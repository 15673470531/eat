<?php
namespace App\Console\Commands;
use App\Services\AboutSettings;
use Illuminate\Console\Command;
class AboutSetCommand extends Command {
 protected $signature = 'about:set {field? : 要改的字段} {value? : 新值} {--forget : 删掉该字段，退回默认值}';
 protected $description = '查看或修改「关于我们」内容（app_settings.about）；换公众号文章链接不用发小程序版本';
 public function handle(): int {
  $field = $this->argument('field'); $value = $this->argument('value');
  if ($field === null || $field === '') return $this->show();
  if (!in_array($field, AboutSettings::fields(), true)) {
   $this->error('不支持的字段：'.$field.'；可用字段：'.implode('、', AboutSettings::fields()));
   return self::FAILURE;
  }
  if ($this->option('forget')) $data = AboutSettings::forget($field);
  elseif ($value === null || $value === '') {
   $this->error('请给 '.$field.' 提供新值，或加 --forget 退回默认值。');
   return self::FAILURE;
  } else $data = AboutSettings::put($field, $value);
  $this->info('已更新 '.$field.'，当前内容：');
  $this->line(json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
  return self::SUCCESS;
 }
 // 不带参数时列出当前内容，方便上线前确认
 private function show(): int {
  $this->line(json_encode(AboutSettings::read(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
  return self::SUCCESS;
 }
}
