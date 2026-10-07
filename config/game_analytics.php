<?php

return [
    'enabled' => env('GAME_ANALYTICS_ENABLED', true),
    'game_id' => 'kdtl',
    'events' => [
        'kdtl_run_start', 'kdtl_run_resume', 'kdtl_wave_complete', 'kdtl_run_death',
        'kdtl_run_complete', 'kdtl_settle_action', 'kdtl_run_leave', 'kdtl_run_revive',
        'kdtl_app_hide', 'kdtl_app_show',
    ],
];
