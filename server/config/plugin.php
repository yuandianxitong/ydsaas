<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
return [
    // 插件市场分类码表 —— plugin.json 的 category 取值范围。
    // key 为机器值；未知/缺省统一归 'other'。可随时增删（前端 tenant 侧需同步镜像）。
    'categories' => [
        'business'  => ['label' => '业务应用', 'sort' => 1],
        'marketing' => ['label' => '营销玩法', 'sort' => 2],
        'channel'   => ['label' => '渠道对接', 'sort' => 3],
        'data'      => ['label' => '数据工具', 'sort' => 4],
        'utility'   => ['label' => '辅助工具', 'sort' => 5],
        'other'     => ['label' => '其他',     'sort' => 99],
    ],
];
