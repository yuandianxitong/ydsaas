<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

use app\repository\plugin\PluginRepository;
use app\service\saas\EntitlementService;
use core\exception\BusinessException;
use think\facade\App;

/**
 * 按租户权益，把 server/plugins/{code}/uniapp/ 复制到产物的
 * {uniappDir}/src/{subpackage}/。
 *
 * 与 TemplateCopier 解耦：TemplateCopier 从干净壳出发（跳过 src/modules 下的
 * 开发态符号链接），本类只复制本租户授权的插件源码 —— 杜绝未授权插件源码
 * 落入构建目录。
 */
final class PluginUniappCopier
{
    public function __construct(
        private readonly PluginRepository $pluginRepository,
        private readonly EntitlementService $entitlementService,
    ) {
    }

    /**
     * @param array<int, string> $pluginCodes  本次构建允许的 plugin code 列表（来自 PagesJsonGenerator.includedPlugins）
     * @return array<int, string>              实际复制成功的 plugin code 列表
     */
    public function copyAuthorized(string $uniappDir, array $pluginCodes): array
    {
        $serverPluginsDir = rtrim(App::getRootPath(), '/') . '/plugins';
        $copied = [];
        foreach ($pluginCodes as $code) {
            $plugin = $this->pluginRepository->findByCode($code);
            if (!$plugin) {
                // 调用方传的 code 来自 PagesJsonGenerator.includedPlugins，
                // 那里已经过 PluginRepository 查找；这里再次找不到只能说明 DB
                // 行被并发删除，直接静默跳过即可
                continue;
            }
            $manifest = is_array($plugin['manifest'] ?? null)
                ? $plugin['manifest']
                : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);
            $subpackage = (string) ($manifest['uniapp']['subpackage'] ?? '');
            if ($subpackage === '') {
                // 同上，generator 已经把声明了 subpackage 的插件加进 includedPlugins；
                // 这里 manifest 又没 subpackage 说明刚被改坏，跳过
                continue;
            }
            $src = $serverPluginsDir . '/' . $code . '/uniapp';
            if (!is_dir($src)) {
                // v2.6.1：源码缺失是部署/同步事故，必须早期失败。
                // 不抛会让 build 继续，最后 uni build 才报「页面不存在」，错误难定位。
                throw new BusinessException(
                    sprintf(
                        '[mobile-builder] 插件 %s 已声明 uniapp.subpackage=%s 但源码目录不存在：%s。'
                            . '请检查 server/plugins/%s/uniapp/ 是否随代码同步到部署环境。',
                        $code,
                        $subpackage,
                        $src,
                        $code,
                    ),
                    500,
                );
            }
            $dst = $uniappDir . '/src/' . trim($subpackage, '/');
            $this->copyDir($src, $dst);
            $copied[] = $code;
        }
        return $copied;
    }

    /**
     * DIY 渲染器协议 v1：把授权插件的 uniapp/components/diy/ 复制到产物**主包**路径
     * {uniappDir}/src/components/diy/plugins/{code}/。
     *
     * 与 copyAuthorized 不同：不依赖 includedPlugins（那个列表要求插件声明了
     * uniapp.subpackage + pages，而纯 DIY 渲染器插件可以没有分包页面），直接按租户
     * 权益遍历。主包路径是必须的 —— DIY 首页在主包，小程序主包不能引用分包资源。
     *
     * @return array<int, string> 实际复制的 plugin code 列表
     */
    public function copyDiyRenderers(string $uniappDir, int $tenantId): array
    {
        $serverPluginsDir = rtrim(App::getRootPath(), '/') . '/plugins';
        $copied = [];
        foreach ($this->entitlementService->list($tenantId) as $ent) {
            $plugin = $this->pluginRepository->findByEntitlement($ent->code);
            if (!$plugin) {
                continue;
            }
            $code = (string) ($plugin['code'] ?? '');
            $src = $serverPluginsDir . '/' . $code . '/uniapp/components/diy';
            if ($code === '' || !is_dir($src)) {
                continue; // 该插件没有 uniapp DIY 渲染器，正常跳过
            }
            $this->copyDir($src, $uniappDir . '/src/components/diy/plugins/' . $code);
            $copied[] = $code;
        }
        return $copied;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
            throw new \RuntimeException("failed to mkdir {$dst}");
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iter as $item) {
            $relative = substr($item->getPathname(), strlen($src) + 1);
            $dstPath  = $dst . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($dstPath) && !mkdir($dstPath, 0775, true) && !is_dir($dstPath)) {
                    throw new \RuntimeException("failed to mkdir {$dstPath}");
                }
            } else {
                copy($item->getPathname(), $dstPath);
            }
        }
    }
}
