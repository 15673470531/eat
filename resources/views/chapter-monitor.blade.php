<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>第一章监控</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#182236;font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}main{max-width:1380px;margin:auto;padding:28px 24px}h1{font-size:26px;margin:0 0 20px}h2,p{margin:0 0 8px}input,button{font:inherit;color:inherit}button{background:none;border:0;padding:0}.cm-box{background:white}.cm-grid{align-items:end}.cm-metric{align-self:stretch}.cm-action{white-space:nowrap}@media(max-width:600px){main{padding:18px 12px}.cm-grid label{flex:1;min-width:140px}.cm-table{min-width:650px}}
    </style>
</head>
<body><main>
    <h1>第一章监控</h1>
    @isset($errorMessages)
        <div role="alert">@foreach($errorMessages as $messages)@foreach($messages as $message)<p>{{ $message }}</p>@endforeach@endforeach</div>
        <a href="{{ route('chapter-monitor.public') }}">返回监控页，重新筛选</a>
    @else
        @include('partials.chapter-monitor-content')
    @endisset
</main></body>
</html>
