<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

use app\repository\saas\TenantRepository;
use app\service\saas\TenantMobileConfigService;
use think\facade\App;

/**
 * 生成租户专属构建工作区：复制 uniapp 模板 → 生成 pages.json → 注入 manifest.json
 * → 写 tenant-config.ts → 复制授权插件源码，产出 MobileBuildContext。
 *
 * 逻辑由原 DefaultMobileBuilder::build() 第 1~5 步平移而来，行为不变。
 * 不执行 pnpm/uni build——那是 MobileBuildDriver 的职责。
 */
final class MobileBuildPreparer
{
    public function __construct(
        private readonly TemplateCopier $copier,
        private readonly PagesJsonGenerator $pagesGenerator,
        private readonly ManifestInjector $manifestInjector,
        private readonly TenantConfigWriter $tenantConfigWriter,
        private readonly TenantMobileConfigService $mobileConfigService,
        private readonly TenantRepository $tenantRepository,
        private readonly PluginUniappCopier $pluginUniappCopier,
        private readonly DiyRendererRegistryGenerator $diyRendererRegistryGenerator,
    ) {
    }

    public function prepare(int $tenantId, int $buildId, string $platform): MobileBuildContext
    {
        $rootPath = App::getRootPath();
        // server/ 是 root，模板在 ../uniapp
        $templateDir = realpath($rootPath . '../uniapp');
        if ($templateDir === false || !is_dir($templateDir)) {
            throw new \RuntimeException("[builder] uniapp template not found at {$rootPath}../uniapp");
        }

        $targetDir = $rootPath . 'runtime/mobile-builds/' . $tenantId . '/' . $buildId;

        // 1. 复制模板
        $uniappDir = $this->copier->copy($templateDir, $targetDir);

        // 2. 取租户配置（pages.json 生成需要 tabbar）
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("[builder] tenant {$tenantId} not found");
        }
        $tenantCode   = (string) ($tenant['tenant_code'] ?? '');
        $mobileConfig = $this->mobileConfigService->get($tenantId);
        $tabbar       = is_array($mobileConfig['tabbar'] ?? null) ? $mobileConfig['tabbar'] : [];

        // #region agent log
        $__hd = $mobileConfig['home_decoration'] ?? null;
        $__log = [
            'sessionId' => 'ddd612',
            'hypothesisId' => 'A',
            'location' => 'MobileBuildPreparer.php:get',
            'message' => 'mobile config at prepare',
            'data' => [
                'tenantId' => $tenantId,
                'tenantCode' => $tenantCode,
                'buildId' => $buildId,
                'platform' => $platform,
                'tabbarCount' => count($tabbar),
                'tabbarTexts' => array_values(array_map(static fn ($i) => (string) ($i['text'] ?? ''), $tabbar)),
                'tabbarPaths' => array_values(array_map(static fn ($i) => (string) ($i['path'] ?? ''), $tabbar)),
                'homeDecorationNull' => $__hd === null,
                'homeDecorationComponents' => is_array($__hd) ? count($__hd['components'] ?? []) : 0,
                'homePage' => (string) ($mobileConfig['home_page'] ?? ''),
            ],
            'timestamp' => (int) (microtime(true) * 1000),
        ];
        $__logPath = dirname(rtrim(App::getRootPath(), '/\\')) . '/.cursor/debug-ddd612.log';
        if (!is_dir(dirname($__logPath))) {
            @mkdir(dirname($__logPath), 0775, true);
        }
        @file_put_contents($__logPath, json_encode($__log, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        // #endregion

        // 3. 按权益生成 pages.json（含租户 tabBar 注入），写到产物 src/pages.json
        $pagesResult = $this->pagesGenerator->generate($tenantId, $uniappDir, $tabbar);
        file_put_contents(
            $uniappDir . '/src/pages.json',
            json_encode($pagesResult['pagesJson'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        // 3.5 按权益复制插件 uniapp 源码
        $this->pluginUniappCopier->copyAuthorized($uniappDir, $pagesResult['includedPlugins']);

        // 3.6 DIY 渲染器（协议 v1）：授权插件渲染器复制到主包 + 按权益重写静态注册表
        //（顺序不可倒置：注册表只收录已复制到产物的组件文件）
        $this->pluginUniappCopier->copyDiyRenderers($uniappDir, $tenantId);
        $this->diyRendererRegistryGenerator->write($uniappDir, $tenantId);

        // 4. 注入 manifest.json
        $manifestJson = $this->manifestInjector->inject($uniappDir, $mobileConfig, $platform);

        // 5. 写 tenant-config.ts
        $this->tenantConfigWriter->write($uniappDir, $tenantId, $tenantCode, $mobileConfig);

        return new MobileBuildContext(
            tenantId: $tenantId,
            buildId: $buildId,
            platform: $platform,
            tenantCode: $tenantCode,
            workspaceDir: $targetDir,
            uniappDir: $uniappDir,
            includedPlugins: $pagesResult['includedPlugins'],
            pagesJson: $pagesResult['pagesJson'],
            manifestJson: $manifestJson,
        );
    }
}
