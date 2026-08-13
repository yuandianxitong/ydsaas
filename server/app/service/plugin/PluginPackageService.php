<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin;

use app\model\plugin\Plugin;
use app\repository\plugin\PluginRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\PluginManifestValidator;
use core\plugin\PluginPackageInstaller;
use core\plugin\PluginScanner;
use core\runtime\RuntimePaths;
use think\facade\App;

/**
 * 处理插件 zip 上传 + dry-run 校验。
 *
 * 不直接执行安装：仅把 zip 保存到 runtime/plugin-packages/、在 plugins 表写一行
 * status=STATUS_INSTALLING 等待 PluginService::install() 接管。
 */
class PluginPackageService extends Service
{
    protected PluginPackageInstaller $installer;
    protected PluginManifestValidator $manifestValidator;
    protected PluginRepository $pluginRepo;
    protected PluginScanner $scanner;

    /**
     * @param string $tmpZipPath 上传 framework 给的临时文件路径
     * @return array<string, mixed> {plugin_id, manifest, dry_run_ok}
     */
    public function upload(string $tmpZipPath): array
    {
        // 1. dry-run inspect (zip 安全校验 + 解析 manifest)
        $manifest = $this->installer->inspect($tmpZipPath);

        // 2. manifest 字段校验
        $errors = $this->manifestValidator->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException('plugin.json 校验失败: ' . implode('; ', $errors), 422);
        }

        $code = (string) $manifest['code'];

        // 3. 防重复：包含软删行一起查（uk_code 唯一索引不区分软删）
        //    - 软删行：视为可"复活"，下方 update 时一并清掉 deleted_at
        //    - 活跃且非 FAILED：拒绝
        //    - 活跃 FAILED：覆盖（保留旧 id 重试）
        $existing = $this->pluginRepo->findByCodeWithTrashed($code);
        $isTrashed = $existing && !empty($existing['deleted_at']);
        if ($existing && !$isTrashed && (int) ($existing['status'] ?? 0) !== Plugin::STATUS_FAILED) {
            throw new BusinessException("插件 [{$code}] 已存在 (status={$existing['status']})，请先卸载后再上传", 409);
        }

        // 4. 备份 zip 到 runtime/plugin-packages/<code>-<version>.zip
        $packageDir = RuntimePaths::pluginPackagesDir(App::getRootPath());
        if (!is_dir($packageDir)) {
            mkdir($packageDir, RuntimePaths::DIR_MODE, true);
        }
        $packagePath = $packageDir . '/' . $code . '-' . $manifest['version'] . '.zip';
        if (!copy($tmpZipPath, $packagePath)) {
            throw new BusinessException('zip 备份失败', 500);
        }

        $packageHash = hash_file('sha256', $packagePath) ?: '';

        // 5. 在 plugins 表写一行（或更新失败行重试）
        $pluginId = $this->runInTransaction(function () use ($existing, $manifest, $packagePath, $packageHash) {
            if ($existing) {
                // 复活软删行 or 覆盖 FAILED 行：raw update 绕开 SoftDelete trait
                // 否则 Repository::update 的 query() 带 deleted_at IS NULL 改不到软删行
                $row = $this->buildPluginRow($manifest, $packagePath, $packageHash, Plugin::SOURCE_ZIP);
                $this->pluginRepo->reviveAndUpdate((int) $existing['id'], $row);
                return (int) $existing['id'];
            }
            return $this->createPluginRow($manifest, $packagePath, $packageHash, Plugin::SOURCE_ZIP);
        });

        return [
            'plugin_id'  => $pluginId,
            'manifest'   => $manifest,
            'dry_run_ok' => true,
        ];
    }

    /**
     * 从磁盘登记一个本地（git 一起带的）插件到 plugins 表，
     * 然后由调用方继续调 PluginService::install() 走标准安装链路。
     *
     * 区别于 upload()：
     *   - 不要 zip，也不备份到 runtime/plugin-packages/
     *   - source = SOURCE_BUILTIN
     *   - PluginService::install 看到 source=BUILTIN 时会跳过 zip 解压步骤
     *
     * 幂等：FAILED / 软删行会被复活覆盖。
     */
    public function registerFromDisk(string $code): array
    {
        $manifests = $this->scanner->scan();
        if (!isset($manifests[$code])) {
            throw new BusinessException("磁盘上未找到插件 [{$code}]，请确认 server/plugins/{$code}/plugin.json 存在且 manifest 合法", 404);
        }
        $manifest = $manifests[$code];

        // manifest 校验（scanner 已经过滤过，这里防御性兜底）
        $errors = $this->manifestValidator->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException('plugin.json 校验失败: ' . implode('; ', $errors), 422);
        }

        $existing = $this->pluginRepo->findByCodeWithTrashed($code);
        $isTrashed = $existing && !empty($existing['deleted_at']);
        if ($existing && !$isTrashed && (int) ($existing['status'] ?? 0) !== Plugin::STATUS_FAILED) {
            throw new BusinessException("插件 [{$code}] 已存在 (status={$existing['status']})，无需重复登记", 409);
        }

        $pluginId = $this->runInTransaction(function () use ($existing, $manifest) {
            if ($existing) {
                $row = $this->buildPluginRow($manifest, '', '', Plugin::SOURCE_BUILTIN);
                $this->pluginRepo->reviveAndUpdate((int) $existing['id'], $row);
                return (int) $existing['id'];
            }
            return $this->createPluginRow($manifest, '', '', Plugin::SOURCE_BUILTIN);
        });

        return [
            'plugin_id' => $pluginId,
            'manifest'  => $manifest,
        ];
    }

    /**
     * 构建 plugins 表的行数组，不写库。
     * 在 upload() / registerFromDisk() 的 revive 分支中使用（需要先 reviveAndUpdate）。
     *
     * @param array<string, mixed> $manifest  已通过 validate 的 manifest（由调用方保证）
     * @param string               $source    Plugin::SOURCE_ZIP | SOURCE_BUILTIN
     * @return array<string, mixed>
     */
    protected function buildPluginRow(array $manifest, string $packagePath, string $packageHash, int $source): array
    {
        $manifest = $this->manifestValidator->normalize($manifest);
        $now      = date('Y-m-d H:i:s');

        return [
            'code'         => (string) $manifest['code'],
            'name'         => (string) ($manifest['name'] ?? $manifest['code']),
            'version'      => (string) $manifest['version'],
            'author'       => (string) ($manifest['author'] ?? ''),
            'description'  => (string) ($manifest['description'] ?? ''),
            'icon'         => (string) ($manifest['icon'] ?? ''),
            'type'         => (int) ($manifest['type'] ?? Plugin::TYPE_BUSINESS),
            'kind'         => in_array(($manifest['kind'] ?? 'plugin'), [Plugin::KIND_APP, Plugin::KIND_PLUGIN], true)
                                ? (string) ($manifest['kind'] ?? Plugin::KIND_PLUGIN)
                                : Plugin::KIND_PLUGIN,
            'entitlement'  => (string) ($manifest['entitlement'] ?? $manifest['code']),
            'depends'      => !empty($manifest['depends'])
                                ? json_encode(array_values((array) $manifest['depends']), JSON_UNESCAPED_UNICODE)
                                : null,
            'source'       => $source,
            'package_path' => $packagePath,
            'package_hash' => $packageHash,
            'manifest'     => json_encode($manifest, JSON_UNESCAPED_UNICODE),
            // Task 17: 把 manifest.read_safe_routes 同步到独立列，供 Task 18
            // RuntimeLicenseGuard 中间件读取做"豁免写请求"判定。
            // 只接受数组类型，标量 / 对象 / 缺失视为未声明（NULL）。
            'read_safe_routes' => isset($manifest['read_safe_routes']) && is_array($manifest['read_safe_routes'])
                ? json_encode(array_values($manifest['read_safe_routes']), JSON_UNESCAPED_UNICODE)
                : null,
            'status'       => Plugin::STATUS_INSTALLING,
            'last_error'   => '',
            'deleted_at'   => null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }

    /**
     * 插入新的 plugins 行并返回新行 id。
     * 供 upload() / registerFromDisk() 的新建分支使用。
     * 也供测试通过反射直接调用以验证写库正确性。
     *
     * @param array<string, mixed> $manifest  已通过 validate 的 manifest（由调用方保证）
     * @param string               $source    Plugin::SOURCE_ZIP | SOURCE_BUILTIN
     */
    protected function createPluginRow(array $manifest, string $packagePath, string $packageHash, int $source = Plugin::SOURCE_ZIP): int
    {
        $row = $this->buildPluginRow($manifest, $packagePath, $packageHash, $source);
        return (int) $this->pluginRepo->create($row)['id'];
    }
}
