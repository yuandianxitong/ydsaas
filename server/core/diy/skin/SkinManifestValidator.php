<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\diy\skin;

/**
 * 皮肤包 skin.json 校验（纯逻辑，无 IO）。
 */
final class SkinManifestValidator
{
    private const VERSION_REGEX = '/^\d+\.\d+\.\d+(-[A-Za-z0-9.-]+)?$/';
    private const CODE_REGEX = '/^[a-z][a-z0-9-]{1,62}[a-z0-9]$/';
    private const SLUG_REGEX = '/^[a-z0-9][a-z0-9-]{0,62}[a-z0-9]$/';

    /**
     * @param array<string,mixed> $manifest
     * @return string[] 空 = 通过
     */
    public function validate(array $manifest): array
    {
        $errors = [];

        if (($manifest['kind'] ?? '') !== 'skin') {
            $errors[] = "kind 必须为 'skin'";
        }

        $code = (string) ($manifest['code'] ?? '');
        if ($code === '' || !preg_match(self::CODE_REGEX, $code)) {
            $errors[] = 'code 不合法（小写字母开头，2-64 位字母数字连字符）';
        }

        $name = trim((string) ($manifest['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 64) {
            $errors[] = 'name 必填且不超过 64 字';
        }

        $version = (string) ($manifest['version'] ?? '');
        if ($version === '' || !preg_match(self::VERSION_REGEX, $version)) {
            $errors[] = 'version 必须符合 semver';
        }

        $min = (string) ($manifest['framework_saas_min'] ?? '');
        if ($min !== '' && !preg_match(self::VERSION_REGEX, $min)) {
            $errors[] = 'framework_saas_min 必须符合 semver';
        }

        $platforms = $manifest['platforms'] ?? ['uniapp'];
        if (!is_array($platforms) || $platforms === []) {
            $errors[] = 'platforms 必须为非空数组';
        } else {
            foreach ($platforms as $p) {
                if (!in_array($p, ['uniapp', 'pc'], true)) {
                    $errors[] = "platforms 含非法值：{$p}";
                }
            }
            if (!in_array('uniapp', $platforms, true)) {
                $errors[] = 'platforms 必须包含 uniapp';
            }
        }

        $pages = $manifest['pages'] ?? [];
        if (!is_array($pages) || $pages === []) {
            $errors[] = 'pages 必须为非空数组';
        } else {
            $hasHome = false;
            $hasMember = false;
            foreach ($pages as $i => $pageKey) {
                $pageKey = (string) $pageKey;
                if ($pageKey === 'home') {
                    $hasHome = true;
                }
                if ($pageKey === 'member') {
                    $hasMember = true;
                }
                if ($pageKey !== 'home' && $pageKey !== 'member' && !preg_match(self::SLUG_REGEX, $pageKey)) {
                    $errors[] = "pages[{$i}] 标识不合法：{$pageKey}";
                }
            }
            if (!$hasHome) {
                $errors[] = 'pages 必须包含 home';
            }
            if (!$hasMember) {
                $errors[] = 'pages 必须包含 member';
            }
        }

        foreach (['requires_apps', 'requires_plugins'] as $field) {
            $list = $manifest[$field] ?? [];
            if (!is_array($list)) {
                $errors[] = "{$field} 必须为数组";
                continue;
            }
            foreach ($list as $i => $codeItem) {
                if (!is_string($codeItem) || $codeItem === '' || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $codeItem)) {
                    $errors[] = "{$field}[{$i}] 不合法";
                }
            }
        }

        $rec = $manifest['recommended_for_app'] ?? null;
        if ($rec !== null && $rec !== '') {
            if (!is_string($rec) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $rec)) {
                $errors[] = 'recommended_for_app 不合法';
            }
        }

        return $errors;
    }
}
