<?php

// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'yd:update' => \app\command\YdUpdateCommand::class,
        \app\command\MakeCrudCommand::class,
        \app\command\GenerateApiDocCommand::class,
        'log:archive' => \app\command\LogArchiveCommand::class,
        \app\command\SaasCreatePlatformAdmin::class,
        \app\command\SaasTenantLifecycle::class,
        'saas:order-cleanup' => \app\command\SaasOrderCleanup::class,
        'saas:install'       => \app\command\SaasInstallCommand::class,
        'saas:ensure-runtime' => \app\command\SaasEnsureRuntime::class,
        'saas:plugin-menu-reconcile'    => \app\command\SaasPluginMenuReconcile::class,
        'saas:diy-menu-reconcile'  => \app\command\SaasDiyMenuReconcile::class,
        'saas:menu-sync'           => \app\command\SaasMenuSync::class,
        'saas:message-template-sync' => \app\command\SaasMessageTemplateSync::class,
        'cron:run'                 => \app\command\CronRun::class,
        'saas:backfill-grants'       => \app\command\SaasBackfillGrants::class,
        'saas:mobile-build-prune'    => \app\command\SaasMobileBuildPrune::class,
        'saas:marketplace-sync'      => \app\command\SaasMarketplaceSync::class,
        'saas:license-evaluate'      => \app\command\SaasLicenseEvaluate::class,
        'saas:marketplace-token-rotate' => \app\command\SaasMarketplaceTokenRotate::class,
        'license:heartbeat'          => \app\command\LicenseHeartbeatCommand::class,
        'plugin:create' => \app\command\PluginCreate::class,
        'plugin:pack'   => \app\command\PluginPack::class,
    ],
];
