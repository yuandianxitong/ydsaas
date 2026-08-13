<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],

        // ---- 管理员事件 ----
        'admin.login.success' => [\app\listener\system\AdminLoginSuccessListener::class],
        'admin.login.failed'  => [\app\listener\system\AdminLoginFailedListener::class],

        // ---- 系统配置事件 ----
        'config.changed' => [\app\listener\system\ConfigChangedListener::class],

        // ---- 菜单事件 ----
        'menu.changed' => [\app\listener\system\MenuChangedListener::class],

        // ---- 用户事件 ----
        'user.register' => [\app\listener\user\UserRegisterListener::class],
        'user.login'    => [\app\listener\user\UserLoginListener::class],

        // ---- 支付事件 ----
        'payment.success' => [\app\listener\payment\PaymentSuccessListener::class],
        'refund.success'  => [\app\listener\payment\RefundSuccessListener::class],

        // ---- 消息事件调度 ----
        'message.event.dispatch' => [\app\listener\message\MessageEventListener::class],

        // ---- 反馈事件 ----
        'feedback.created' => [\app\listener\feedback\FeedbackCreatedListener::class],

        // ---- 消息推送事件 ----
        'message.created' => [\app\listener\MessagePushListener::class],

        // ---- 市场集成事件 ----
        'marketplace.connection.bound' => [\app\listener\marketplace\MarketplaceConnectionBoundListener::class],
        'marketplace.app.installed'    => [\app\listener\marketplace\MarketplaceInstallSucceededListener::class],
        'marketplace.app.upgraded'     => [\app\listener\marketplace\MarketplaceInstallSucceededListener::class],

        // ---- 插件生命周期事件（匿名审计上报）----
        'plugin.uninstalled' => [\app\listener\marketplace\PluginUninstalledListener::class],
        'plugin.rolled_back' => [\app\listener\marketplace\PluginRolledBackListener::class],

        // 预留事件（暂无监听器）
        'article.created'            => [],
        'user.notification.created'  => [],
    ],

    'subscribe' => [
    ],
];
