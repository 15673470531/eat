<?php

return [
    'enabled' => env('GAME_ANALYTICS_ENABLED', true),
    'game_id' => 'kdtl',
    'events' => [
        'kdtl_run_start', 'kdtl_run_resume', 'kdtl_wave_complete', 'kdtl_run_death',
        'kdtl_run_complete', 'kdtl_settle_action', 'kdtl_run_leave', 'kdtl_run_revive',
        'kdtl_app_hide', 'kdtl_app_show',
        // 2026-10 新增：会话/教学漏斗/关卡漏斗/结算快照。
        // ⚠️ 客户端同名白名单在 platform/wechat/analytics.js 的 EVENTS —— 加事件必须**两处一起加**：
        //    客户端漏了 = 静默丢弃（不报错），服务端漏了 = 422 拒收（整批事件被客户端当坏记录丢掉）。
        // ⚠️ 发布顺序必须**先服务端（这里）再客户端**：服务端白名单是守门人。
        //    老客户端继续发旧事件，白名单是并集 → 向后兼容，不需要同时上线。
        // 这 6 个事件**没有引入新字段**，全部信息塞在 detail（≤64 字符）里 → 不需要迁移/改表。
        'kdtl_launch',      // 启动来源：detail = s:<微信 scene>
        'kdtl_tut_step',    // 教学每一步：detail = step:<N>
        'kdtl_tut_end',     // 序章完成：detail = done
        'kdtl_login',       // 登录结果：detail = ok|fail
        'kdtl_stage_enter', // 进入下一关：detail = in（起始关卡号由 kdtl_run_start 的 stage 字段给）
        'kdtl_loadout', 'kdtl_weapon_box', 'kdtl_mod_unlock',
        'kdtl_snapshot',    // 结算快照：detail = g:<金币>,m:<熟练度合计>,uw:<已解锁武器数>
    ],
];
