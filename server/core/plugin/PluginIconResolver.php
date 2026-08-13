<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\plugin;

use think\facade\App;
use think\facade\Db;
use think\Response;

/**
 * v2.7.4：从 server/plugins/<code>/<file> 读插件图标 → Response。
 *
 * 边界：
 * - $code 必须是合法 plugin code（kebab-case），防 path traversal
 * - $file 必须是单段文件名（不含 / 与 ..），扩展名在白名单
 * - 插件必须 status=ENABLED + deleted_at IS NULL（与 listAvailableForGrant 对齐）
 *   —— 已下架/软删插件的图标不再对外暴露
 * - 文件必须 is_file 且大小 ≤ 1MB（安全兜底，避免被换成大文件做 DoS）
 *
 * 任何校验失败一律 404 —— 不区分原因避免信息泄露。
 */
class PluginIconResolver
{
    private const CODE_REGEX = '/^[a-z][a-z0-9-]{1,79}$/';
    private const FILE_REGEX = '/^[a-z0-9][a-z0-9._-]{0,63}\.(png|jpg|jpeg|svg|webp|ico|gif)$/i';
    private const MAX_BYTES  = 1024 * 1024;

    private const MIME = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'gif'  => 'image/gif',
    ];

    public function resolve(string $code, string $file): Response
    {
        if (!preg_match(self::CODE_REGEX, $code) || !preg_match(self::FILE_REGEX, $file)) {
            return $this->notFound();
        }

        // 已申明的 icon 文件名：优先取已启用的 DB 行，没有则回退到磁盘 manifest
        // （让 git 一起带的本地插件在「未安装」虚拟态也能显示图标）。
        // 两路都只认 manifest 声明过的文件 → 防止枚举插件目录里其它文件。
        $declared = $this->declaredIcon($code);
        if ($declared === '' || strcasecmp($declared, $file) !== 0) {
            return $this->notFound();
        }

        return $this->serveFile(App::getRootPath() . 'plugins/' . $code . '/' . $file, $file);
    }

    /**
     * DIY widget 图标服务（渲染器协议扩展）：文件约定放插件 assets/diy/ 下，
     * 只认 manifest diy_widgets[].icon 声明过的文件名（防枚举，同 resolve 策略）。
     * 路由：/plugin-icon/<code>/assets/diy/<file>。
     */
    public function resolveDiyWidgetIcon(string $code, string $file): Response
    {
        if (!preg_match(self::CODE_REGEX, $code) || !preg_match(self::FILE_REGEX, $file)) {
            return $this->notFound();
        }
        if (!in_array(strtolower($file), $this->declaredDiyWidgetIcons($code), true)) {
            return $this->notFound();
        }

        return $this->serveFile(App::getRootPath() . 'plugins/' . $code . '/assets/diy/' . $file, $file);
    }

    private function serveFile(string $path, string $file): Response
    {
        if (!is_file($path)) {
            return $this->notFound();
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_BYTES) {
            return $this->notFound();
        }

        $content = (string) file_get_contents($path);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';
        $etag = '"' . hash('sha256', $content) . '"';

        return Response::create($content)
            ->contentType($mime)
            ->header([
                'Cache-Control' => 'public, max-age=86400, immutable',
                'ETag'          => $etag,
            ]);
    }

    /**
     * 磁盘 manifest 中 diy_widgets[].icon 声明的文件名集合（小写）。
     * 只走磁盘扫描：widget 图标文件本身必须在磁盘（plugins/<code>/assets/diy/）才可服务，
     * 无 DB 独立字段可回退。
     * @return array<int,string>
     */
    private function declaredDiyWidgetIcons(string $code): array
    {
        $manifests = app(PluginScanner::class)->scan();
        $manifest  = $manifests[$code] ?? null;
        if (!is_array($manifest)) {
            return [];
        }
        $out = [];
        foreach ((array) ($manifest['diy_widgets'] ?? []) as $w) {
            $icon = is_array($w) ? ($w['icon'] ?? null) : null;
            if (is_string($icon) && $icon !== '' && !preg_match('#^https?://#i', $icon) && preg_match(self::FILE_REGEX, $icon)) {
                $out[] = strtolower($icon);
            }
        }
        return $out;
    }

    /**
     * 解析某插件 manifest 声明的 icon 文件名。
     * - 已启用且未软删的 DB 行：用其 icon 字段（已安装插件）
     * - 否则回退磁盘扫描：git 一起带的本地插件即使未登记/未安装也能拿到声明的 icon
     * - 找不到返回 ''
     */
    private function declaredIcon(string $code): string
    {
        $row = Db::table('plugins')
            ->where('code', $code)
            ->where('status', \app\model\plugin\Plugin::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->field('icon')
            ->find();
        if ($row) {
            return (string) ($row['icon'] ?? '');
        }

        $manifests = app(PluginScanner::class)->scan();
        $manifest  = $manifests[$code] ?? null;
        return is_array($manifest) ? (string) ($manifest['icon'] ?? '') : '';
    }

    private function notFound(): Response
    {
        return Response::create('', 'html', 404);
    }

    /**
     * 把 manifest 的 icon 值转为浏览器可拉的 URL。
     * - 空 → 返回 ''（前端走渐变色兜底字母）
     * - http(s):// 开头 → 透传（暂未启用但保留扩展点）
     * - 简单文件名（icon.png 等） → 拼成 /plugin-icon/<code>/<file>
     * - 其它非法值（含 /、..） → 返回 ''
     */
    public static function iconUrl(string $code, string $iconValue): string
    {
        if ($iconValue === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $iconValue)) {
            return $iconValue;
        }
        if (!preg_match(self::FILE_REGEX, $iconValue) || !preg_match(self::CODE_REGEX, $code)) {
            return '';
        }
        return '/plugin-icon/' . $code . '/' . $iconValue;
    }

    /**
     * 把 diy_widgets[].icon 值转为浏览器可拉的 URL（约定 assets/diy/ 子目录通道）。
     * 规则同 iconUrl：空/非法 → ''；http(s) 透传；文件名 → /plugin-icon/<code>/assets/diy/<file>
     */
    public static function diyWidgetIconUrl(string $code, string $iconValue): string
    {
        if ($iconValue === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $iconValue)) {
            return $iconValue;
        }
        if (!preg_match(self::FILE_REGEX, $iconValue) || !preg_match(self::CODE_REGEX, $code)) {
            return '';
        }
        return '/plugin-icon/' . $code . '/assets/diy/' . $iconValue;
    }
}
