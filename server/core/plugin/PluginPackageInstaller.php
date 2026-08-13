<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;
use ZipArchive;

/**
 * 插件 zip 安装器。
 *
 * inspect(): 校验 zip 结构（路径白名单 + 扩展名白名单 + PHP 静态扫描）+ 解析 plugin.json，不解压到磁盘
 * extract(): 校验后真正解压到目标目录（plugins/<code>/）
 *
 * 两阶段分离让 PluginPackageService 可以先 dry-run，等用户确认后再 extract。
 */
class PluginPackageInstaller
{
    private const MAX_SIZE = 50 * 1024 * 1024; // 50 MB

    private const ALLOWED_EXTENSIONS = [
        'php', 'ts', 'tsx', 'vue', 'js', 'mjs', 'cjs',
        'json', 'yaml', 'yml',
        'css', 'scss', 'less',
        'png', 'jpg', 'jpeg', 'svg', 'ico', 'gif', 'webp',
        'md', 'txt', 'sql',
    ];

    // v2.6.6：Phase A 目录硬切换 —— 旧顶层目录（src/admin/hooks/frontend/resource）废弃，
    // 全部收敛到 app/（含 controller/service/repository/model/hooks）+ tenant/ + uniapp/。
    // v2.28.0：新增 pc/（Nuxt 门户页面）+ database/（install.sql + updates/vX.Y.Z.sql）取代 migrations|migration，
    // 旧 migrations/（复数）、migration/（单数）布局硬拒绝，见 validateEntries 内显式检查。
    //
    // 唯一真源：PluginPacker::pack() 打包时引用的目录集就是本常量（\core\plugin\PluginPackageInstaller::ALLOWED_TOP_DIRS），
    // 两者共用同一份定义，禁止在别处复制副本，避免打包/校验白名单漂移。
    public const ALLOWED_TOP_DIRS = [
        'app', 'tenant', 'uniapp', 'pc', 'database', 'Config', 'config',
        'tests', // tests: 插件测试随包分发(闭源插件的测试与备份载体)
        'assets', // assets: 插件静态资源（assets/diy/ 存 DIY widget 图标，经 /plugin-icon 服务）
    ];

    private const ALLOWED_TOP_FILES = [
        'plugin.json', 'README.md', 'LICENSE', 'CHANGELOG.md',
    ];

    private const DANGEROUS_PATTERNS = [
        'eval'            => '/\beval\s*\(/i',
        'assert'          => '/\bassert\s*\(/i',
        'system'          => '/\bsystem\s*\(/i',
        'exec'            => '/\bexec\s*\(/i',
        'shell_exec'      => '/\bshell_exec\s*\(/i',
        'passthru'        => '/\bpassthru\s*\(/i',
        'proc_open'       => '/\bproc_open\s*\(/i',
        'popen'           => '/\bpopen\s*\(/i',
        'pcntl_exec'      => '/\bpcntl_exec\s*\(/i',
        'create_function' => '/\bcreate_function\s*\(/i',
        // 注意：PHP 反引号操作符（shell 执行）已通过 shell_exec/system/passthru/exec/proc_open/popen/pcntl_exec
        // 几个函数黑名单覆盖；而 MySQL 反引号引用标识符（e.g. CREATE TABLE `foo`）是合法常见用法。
        // 单凭 regex 难以区分两者，故不扫描反引号，避免大量误伤。
        'dynamic_include' => '/\b(?:include|require)(?:_once)?\s+\$/',
    ];

    /**
     * 校验 zip + 解析 plugin.json，不解压到磁盘。
     *
     * @return array<string, mixed> 解析后的 manifest
     */
    public function inspect(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            throw new BusinessException("zip 文件不存在：{$zipPath}", 422);
        }
        if (filesize($zipPath) > self::MAX_SIZE) {
            throw new BusinessException('zip 文件超过 50MB 限制', 422);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new BusinessException('zip 文件无法打开（可能损坏）', 422);
        }

        try {
            $manifest = $this->extractManifest($zip);
            $this->validateEntries($zip);
            $this->scanPhpEntries($zip);
            $this->validateSqlCleanup($zip);
            $this->validateDiyRenderers($zip, $manifest);
            return $manifest;
        } finally {
            $zip->close();
        }
    }

    /**
     * 解压到目标目录（调用前必须 inspect 过）。
     */
    public function extract(string $zipPath, string $targetDir): void
    {
        if (is_dir($targetDir)) {
            throw new BusinessException("目标目录已存在：{$targetDir}", 409);
        }
        if (!mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
            throw new BusinessException("无法创建目录：{$targetDir}", 500);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new BusinessException('zip 文件无法打开', 422);
        }
        try {
            $this->validateEntries($zip);
            if (!$zip->extractTo($targetDir)) {
                throw new BusinessException('zip 解压失败', 500);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractManifest(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('plugin.json');
        if ($raw === false) {
            throw new BusinessException('zip 内 plugin.json 不存在（必须在 zip 根目录）', 422);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new BusinessException('plugin.json 不是合法 JSON', 422);
        }
        return $decoded;
    }

    private function validateEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // 跳过目录条目
            if (str_ends_with($name, '/')) {
                continue;
            }

            // 路径安全
            if (str_starts_with($name, '/') || str_contains($name, '..')) {
                throw new BusinessException("zip 内路径不安全（绝对路径或 ..）：{$name}", 422);
            }

            // v2.28.0：migrations/（复数）、migration/（单数）旧布局硬拒绝，
            // 正则兼容 zip 顶层带/不带插件目录前缀两种打包方式。
            if (preg_match('#^(?:[^/]+/)?migrations?/#', $name)) {
                throw new BusinessException(
                    'migrations/ 布局已废弃（v2.28.0 起）：请改用 database/install.sql + database/updates/vX.Y.Z.sql，详见插件开发文档',
                    422
                );
            }

            // 扩展名白名单（先检查扩展名，确保危险扩展立即被拒绝）
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== '' && !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                throw new BusinessException("zip 内扩展名不允许：.{$ext}（{$name}）", 422);
            }

            // 顶层文件/目录白名单
            $parts = explode('/', $name);
            $top   = $parts[0];
            if (count($parts) === 1) {
                $imageExts = ['png', 'jpg', 'jpeg', 'svg', 'ico', 'gif', 'webp'];
                $ext = strtolower(pathinfo($top, PATHINFO_EXTENSION));
                $isAllowedTopFile = in_array($top, self::ALLOWED_TOP_FILES, true)
                    || in_array($ext, $imageExts, true);
                if (!$isAllowedTopFile) {
                    throw new BusinessException("zip 顶层文件不在白名单：{$name}", 422);
                }
            } else {
                if (!in_array($top, self::ALLOWED_TOP_DIRS, true)) {
                    throw new BusinessException("zip 顶层目录不在白名单：{$top}/", 422);
                }
            }
        }
    }

    /**
     * 宽校验：声明了建表就必须声明清理（purge 依赖 uninstall.sql 删表）。
     * install.sql 不存在（zip 内没有 database/ 目录）时不校验。
     */
    private function validateSqlCleanup(ZipArchive $zip): void
    {
        $installSqlContent   = $zip->getFromName('database/install.sql');
        $uninstallSqlContent = $zip->getFromName('database/uninstall.sql');

        if ($installSqlContent !== false
            && preg_match('/\bCREATE\s+TABLE\b/i', $installSqlContent)
            && trim((string) $uninstallSqlContent) === '') {
            throw new BusinessException(
                'database/install.sql 含建表语句时必须提供非空的 database/uninstall.sql（清理数据用）',
                422
            );
        }
    }

    /**
     * DIY 渲染器协议 v1：manifest 声明了 renderer.<端> 的 widget，zip 内必须存在对应组件文件
     * （约定目录 <端>/components/diy/<裸组件名>.vue）。声明格式本身由 PluginManifestValidator 把关，
     * 本方法只做「声明了但文件没打进包」的存在性校验——plugin:pack 复用同一 inspect()，
     * 本地打包即拦截，这是闭源插件唯一备份手段（zip）下必须有的安全网。
     */
    private function validateDiyRenderers(ZipArchive $zip, array $manifest): void
    {
        foreach ((array) ($manifest['diy_widgets'] ?? []) as $i => $w) {
            if (!is_array($w) || !is_array($w['renderer'] ?? null)) {
                continue;
            }
            foreach (['tenant', 'uniapp', 'pc'] as $end) {
                $name = $w['renderer'][$end] ?? null;
                if (!is_string($name) || $name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
                    continue; // 格式错误由 PluginManifestValidator 报告，这里不重复
                }
                $path = "{$end}/components/diy/{$name}.vue";
                if ($zip->locateName($path) === false) {
                    throw new BusinessException(
                        "diy_widgets[{$i}].renderer.{$end} 声明的渲染器组件在 zip 内不存在：{$path}",
                        422
                    );
                }
            }
        }
        // widget 图标（文件名形式，约定 assets/diy/）同闸校验存在性；URL 形式跳过
        foreach ((array) ($manifest['diy_widgets'] ?? []) as $i => $w) {
            $icon = is_array($w) ? ($w['icon'] ?? null) : null;
            if (!is_string($icon) || $icon === '' || preg_match('#^https?://#i', $icon)
                || !preg_match('/^[a-z0-9][a-z0-9._-]{0,63}\.(png|jpg|jpeg|svg|webp|ico|gif)$/i', $icon)) {
                continue; // 格式错误由 PluginManifestValidator 报告
            }
            if ($zip->locateName("assets/diy/{$icon}") === false) {
                throw new BusinessException(
                    "diy_widgets[{$i}].icon 声明的图标在 zip 内不存在：assets/diy/{$icon}",
                    422
                );
            }
        }
    }

    private function scanPhpEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_ends_with(strtolower($name), '.php')) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            foreach (self::DANGEROUS_PATTERNS as $label => $pattern) {
                if (preg_match($pattern, $content)) {
                    throw new BusinessException("PHP 静态扫描命中危险函数 [{$label}]：{$name}", 422);
                }
            }
        }
    }
}
