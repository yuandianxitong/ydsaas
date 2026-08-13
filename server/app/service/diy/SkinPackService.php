<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyPageRepository;
use app\service\saas\EntitlementService;
use app\service\saas\TenantMobileConfigService;
use app\service\saas\TenantQuotaService;
use app\service\system\FileService;
use core\base\Service;
use core\diy\DiyWidgetCatalog;
use core\diy\DiyWidgetRegistry;
use core\diy\skin\SkinZip;
use core\exception\BusinessException;
use core\runtime\RuntimePaths;
use core\storage\StorageManager;
use core\tenant\TenantContext;
use think\facade\Cache;

/**
 * 整套皮肤包：导出 / 导入预检 / 套用。
 */
class SkinPackService extends Service
{
    protected DiyPageRepository $repo;
    protected DiyPageService $diyPageService;
    protected TenantMobileConfigService $mobileConfigService;
    protected EntitlementService $entitlementService;
    protected DiyWidgetCatalog $widgetCatalog;
    protected FileService $fileService;
    protected TenantQuotaService $tenantQuotaService;

    private const SYSTEM_PAGES = ['home' => '首页', 'member' => '个人中心'];
    private const IMPORT_TTL = 1800;

    /**
     * 列出官方皮肤包（runtime/skin-packages/*.zip）。
     *
     * @return array<int,array{code:string,name:string,version:string,filename:string,size:int}>
     */
    public function listOfficial(): array
    {
        $dir = RuntimePaths::skinPackagesDir(root_path());
        if (!is_dir($dir)) {
            return [];
        }
        $zip = new SkinZip();
        $out = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $file) {
            try {
                $extracted = $zip->extractToTemp($file);
                $m = $extracted['manifest'];
                $zip->removeDir($extracted['dir']);
                $out[] = [
                    'code'     => (string) ($m['code'] ?? pathinfo($file, PATHINFO_FILENAME)),
                    'name'     => (string) ($m['name'] ?? ''),
                    'version'  => (string) ($m['version'] ?? ''),
                    'filename' => basename($file),
                    'size'     => (int) filesize($file),
                ];
            } catch (\Throwable) {
                continue;
            }
        }
        usort($out, static fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $out;
    }

    /** 官方皮肤包绝对路径；不存在抛 404。 */
    public function officialZipPath(string $code): array
    {
        $code = trim($code);
        if ($code === '' || !preg_match('/^[a-z][a-z0-9-]{1,62}[a-z0-9]$/', $code)) {
            throw new BusinessException('皮肤编码不合法', 422);
        }
        foreach ($this->listOfficial() as $item) {
            if ($item['code'] === $code) {
                $path = RuntimePaths::skinPackagesDir(root_path()) . DIRECTORY_SEPARATOR . $item['filename'];
                if (!is_file($path)) {
                    break;
                }

                return ['path' => $path, 'filename' => $item['filename']];
            }
        }
        throw new BusinessException('官方皮肤包不存在', 404);
    }

    /**
     * 导出当前租户皮肤包为 zip 绝对路径。
     *
     * @param array{code?:string,name?:string,include_custom?:bool,page_keys?:array<int,string>} $options
     * @return array{path:string,filename:string}
     */
    public function export(array $options = []): array
    {
        $tid = $this->tenantId();
        $includeCustom = (bool) ($options['include_custom'] ?? true);
        $onlyKeys = isset($options['page_keys']) && is_array($options['page_keys'])
            ? array_values(array_filter(array_map('strval', $options['page_keys'])))
            : null;

        $cfg = $this->mobileConfigService->get($tid);
        $mobile = [
            'theme_color'   => (string) ($cfg['theme_color'] ?? ''),
            'theme_colors'  => is_array($cfg['theme_colors'] ?? null) ? $cfg['theme_colors'] : [],
            'tabbar'        => is_array($cfg['tabbar'] ?? null) ? $cfg['tabbar'] : [],
            'tabbar_style'  => is_array($cfg['tabbar_style'] ?? null) ? $cfg['tabbar_style'] : [],
            'home_app_code' => (string) ($cfg['home_app_code'] ?? ''),
            'home_page'     => (string) ($cfg['home_page'] ?? ''),
        ];

        $rows = $this->repo->listAllForExport();
        $pageFiles = [];
        $pageKeys = [];
        $platforms = ['uniapp'];
        $assetUrls = [];

        foreach ($rows as $row) {
            $platform = (string) ($row['platform'] ?? 'uniapp');
            $pageKey = (string) ($row['page_key'] ?? '');
            $pageType = (string) ($row['page_type'] ?? 'custom');
            if ($pageKey === '') {
                continue;
            }
            if ($platform === 'uniapp') {
                $isSystem = isset(self::SYSTEM_PAGES[$pageKey]);
                if (!$isSystem && !$includeCustom) {
                    continue;
                }
                if (!$isSystem && $onlyKeys !== null && !in_array($pageKey, $onlyKeys, true)) {
                    continue;
                }
            } elseif ($platform === 'pc') {
                if ($pageKey !== 'home') {
                    continue;
                }
            } else {
                continue;
            }

            $components = $this->pickComponents($row);
            $settings = is_array($row['page_settings'] ?? null) ? $row['page_settings'] : [];
            $this->collectUrls($components, $assetUrls);
            $this->collectUrls($settings, $assetUrls);
            $this->collectUrls($mobile, $assetUrls);

            $pageFiles["pages/{$platform}/{$pageKey}"] = [
                'title'         => (string) ($row['title'] ?? $pageKey),
                'page_type'     => $pageType !== '' ? $pageType : (isset(self::SYSTEM_PAGES[$pageKey]) ? $pageKey : 'custom'),
                'components'    => $components,
                'page_settings' => $settings,
            ];
            if ($platform === 'uniapp') {
                $pageKeys[] = $pageKey;
            }
            if ($platform === 'pc' && !in_array('pc', $platforms, true)) {
                $platforms[] = 'pc';
            }
        }

        // 保证 home/member 至少有空页可导出
        foreach (['home', 'member'] as $sys) {
            if (!isset($pageFiles["pages/uniapp/{$sys}"])) {
                $pageFiles["pages/uniapp/{$sys}"] = [
                    'title'         => self::SYSTEM_PAGES[$sys],
                    'page_type'     => $sys,
                    'components'    => [],
                    'page_settings' => [],
                ];
                $pageKeys[] = $sys;
            }
        }
        $pageKeys = array_values(array_unique($pageKeys));

        $tmpAssetDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'skin_assets_' . bin2hex(random_bytes(6));
        if (!mkdir($tmpAssetDir, 0o755, true) && !is_dir($tmpAssetDir)) {
            throw new BusinessException('无法创建资源临时目录', 500);
        }

        $assetFiles = [];
        $urlMap = [];
        try {
            foreach (array_keys($assetUrls) as $url) {
                $packed = $this->packAsset($url, $tmpAssetDir);
                if ($packed === null) {
                    continue;
                }
                $assetFiles[$packed['name']] = $packed['path'];
                $urlMap[$url] = SkinZip::ASSET_PREFIX . $packed['name'];
            }

            foreach ($pageFiles as &$pf) {
                $pf['components'] = $this->rewriteUrls($pf['components'], $urlMap);
                $pf['page_settings'] = $this->rewriteUrls($pf['page_settings'], $urlMap);
            }
            unset($pf);
            $mobile = $this->rewriteUrls($mobile, $urlMap);

            $requires = $this->detectRequires($pageFiles, $mobile);
            $code = (string) ($options['code'] ?? '');
            if ($code === '') {
                $code = 'skin-' . $tid . '-' . date('YmdHis');
            }
            $name = trim((string) ($options['name'] ?? ''));
            if ($name === '') {
                $name = '租户皮肤 ' . date('Y-m-d H:i');
            }

            $manifest = [
                'kind'                 => 'skin',
                'code'                 => $this->sanitizeCode($code),
                'name'                 => mb_substr($name, 0, 64),
                'version'              => '1.0.0',
                'framework_saas_min'   => (string) (config('version.version') ?? '2.33.0'),
                'requires_apps'        => $requires['apps'],
                'requires_plugins'     => $requires['plugins'],
                'recommended_for_app'  => $requires['apps'][0] ?? null,
                'platforms'            => $platforms,
                'pages'                => $pageKeys,
                'exported_at'          => date('c'),
                'exported_tenant_id'   => $tid,
            ];

            $filename = $manifest['code'] . '-' . $manifest['version'] . '.zip';
            $target = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            (new SkinZip())->build($target, $manifest, $mobile, $pageFiles, $assetFiles);

            return ['path' => $target, 'filename' => $filename];
        } finally {
            (new SkinZip())->removeDir($tmpAssetDir);
        }
    }

    /**
     * 上传 zip 做预检，返回 token + 摘要（不落库）。
     *
     * @return array<string,mixed>
     */
    public function importPreview(string $zipPath): array
    {
        $tid = $this->tenantId();
        $zip = new SkinZip();
        $extracted = $zip->extractToTemp($zipPath);

        try {
            $preview = $this->buildPreview($extracted, $tid);
            $token = bin2hex(random_bytes(16));
            $persistDir = runtime_path() . 'skin_import' . DIRECTORY_SEPARATOR . $tid;
            if (!is_dir($persistDir) && !mkdir($persistDir, 0o755, true) && !is_dir($persistDir)) {
                throw new BusinessException('无法创建导入目录', 500);
            }
            $persistZip = $persistDir . DIRECTORY_SEPARATOR . $token . '.zip';
            if (!@copy($zipPath, $persistZip)) {
                throw new BusinessException('无法保存导入包', 500);
            }
            Cache::set($this->importCacheKey($tid, $token), [
                'zip'       => $persistZip,
                'manifest'  => $extracted['manifest'],
                'created_at'=> time(),
            ], self::IMPORT_TTL);

            $preview['token'] = $token;
            $preview['ok'] = empty($preview['missing_apps']) && empty($preview['missing_widgets']) && empty($preview['blocking_errors']);

            return $preview;
        } finally {
            $zip->removeDir($extracted['dir']);
        }
    }

    /**
     * 按预检 token 套用皮肤包。
     *
     * @return array<string,mixed>
     */
    public function apply(string $token, int $createdBy = 0): array
    {
        $tid = $this->tenantId();
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new BusinessException('导入令牌无效', 422);
        }
        $cached = Cache::get($this->importCacheKey($tid, $token));
        if (!is_array($cached) || empty($cached['zip']) || !is_file((string) $cached['zip'])) {
            throw new BusinessException('导入已过期，请重新上传皮肤包', 410);
        }

        $zipPath = (string) $cached['zip'];
        $zip = new SkinZip();
        $extracted = $zip->extractToTemp($zipPath);

        try {
            $preview = $this->buildPreview($extracted, $tid);
            if (!empty($preview['missing_apps']) || !empty($preview['missing_widgets']) || !empty($preview['blocking_errors'])) {
                throw new BusinessException('皮肤包依赖未满足，无法套用', 422, [
                    'missing_apps'    => $preview['missing_apps'],
                    'missing_widgets' => $preview['missing_widgets'],
                    'blocking_errors' => $preview['blocking_errors'],
                ]);
            }

            $urlMap = $this->uploadAssets($extracted['assets_dir'], $tid, $createdBy);

            $appliedPages = [];
            $renamed = [];

            $this->runInTransaction(function () use ($extracted, $urlMap, $createdBy, &$appliedPages, &$renamed): void {
                // 1) DIY pages → draft
                foreach ($extracted['pages'] as $page) {
                    $platform = $page['platform'];
                    $pageKey = $page['page_key'];
                    $pageType = $page['page_type'];
                    $components = $this->rewriteUrls($page['components'], $urlMap);
                    $settings = $this->rewriteUrls($page['page_settings'], $urlMap);

                    if ($platform === 'uniapp') {
                        $finalKey = $pageKey;
                        if ($pageType === 'custom' || (!isset(self::SYSTEM_PAGES[$pageKey]) && $pageKey !== 'home' && $pageKey !== 'member')) {
                            if ($this->repo->existsKey($pageKey)) {
                                $finalKey = $this->nextSkinKey($pageKey);
                                $renamed[$pageKey] = $finalKey;
                            }
                            if ($finalKey !== $pageKey || !$this->repo->existsKey($finalKey)) {
                                $now = date('Y-m-d H:i:s');
                                $this->repo->create([
                                    'page_type'        => 'custom',
                                    'page_key'         => $finalKey,
                                    'platform'         => 'uniapp',
                                    'title'            => $page['title'] !== '' ? $page['title'] : $finalKey,
                                    'components_draft' => [],
                                    'page_settings'    => [],
                                    'status'           => 1,
                                    'created_at'       => $now,
                                    'updated_at'       => $now,
                                ]);
                            }
                            $this->diyPageService->saveDraft($finalKey, $components, $settings);
                            $appliedPages[] = ['platform' => 'uniapp', 'page_key' => $finalKey, 'from' => $pageKey];
                            continue;
                        }

                        // 系统页：先备份再写草稿
                        $this->backupPageIfNeeded($pageKey, 'uniapp', $createdBy);
                        $this->diyPageService->saveDraft($pageKey, $components, $settings);
                        $appliedPages[] = ['platform' => 'uniapp', 'page_key' => $pageKey, 'from' => $pageKey];
                        continue;
                    }

                    if ($platform === 'pc' && $pageKey === 'home') {
                        $this->applyPcHomeDraft($components, $settings, $page['title'], $createdBy);
                        $appliedPages[] = ['platform' => 'pc', 'page_key' => 'home', 'from' => 'home'];
                    }
                }

                // 2) mobile config（主题色 / tabbar / 启动）
                $mobile = $this->rewriteUrls($extracted['mobile'], $urlMap);
                $this->mobileConfigService->save($this->tenantId(), [
                    'theme_color'   => (string) ($mobile['theme_color'] ?? ''),
                    'theme_colors'  => is_array($mobile['theme_colors'] ?? null) ? $mobile['theme_colors'] : [],
                    'tabbar'        => is_array($mobile['tabbar'] ?? null) ? $mobile['tabbar'] : [],
                    'tabbar_style'  => is_array($mobile['tabbar_style'] ?? null) ? $mobile['tabbar_style'] : [],
                    'home_app_code' => (string) ($mobile['home_app_code'] ?? ''),
                    'home_page'     => (string) ($mobile['home_page'] ?? ''),
                ]);
            });

            Cache::delete($this->importCacheKey($tid, $token));
            @unlink($zipPath);

            return [
                'applied_pages' => $appliedPages,
                'renamed'       => $renamed,
                'manifest'      => $extracted['manifest'],
                'hint'          => 'DIY 页面已写入草稿，请预览后手动发布；主题色与底部导航已立即生效',
            ];
        } finally {
            $zip->removeDir($extracted['dir']);
        }
    }

    /**
     * @param array<string,mixed> $extracted
     * @return array<string,mixed>
     */
    private function buildPreview(array $extracted, int $tenantId): array
    {
        $manifest = $extracted['manifest'];
        $blocking = [];
        $warnings = [];

        $min = (string) ($manifest['framework_saas_min'] ?? '');
        $current = (string) (config('version.version') ?? '0.0.0');
        if ($min !== '' && version_compare($current, $min, '<')) {
            $blocking[] = "需要框架版本 ≥ {$min}，当前 {$current}";
        }

        $missingApps = [];
        foreach ((array) ($manifest['requires_apps'] ?? []) as $code) {
            $code = (string) $code;
            if ($code !== '' && !$this->entitlementService->has($tenantId, $code)) {
                $missingApps[] = $code;
            }
        }
        foreach ((array) ($manifest['requires_plugins'] ?? []) as $code) {
            $code = (string) $code;
            if ($code !== '' && !$this->entitlementService->has($tenantId, $code) && !in_array($code, $missingApps, true)) {
                $missingApps[] = $code;
            }
        }

        // 启动首页 / tabbar 依赖
        $mobile = $extracted['mobile'];
        $homeCode = (string) ($mobile['home_app_code'] ?? '');
        if ($homeCode !== '' && !$this->entitlementService->has($tenantId, $homeCode) && !in_array($homeCode, $missingApps, true)) {
            $missingApps[] = $homeCode;
        }
        foreach ((array) ($mobile['tabbar'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = (string) ($item['code'] ?? '');
            if ($code === '' || str_starts_with($code, '__')) {
                continue;
            }
            if (!$this->entitlementService->has($tenantId, $code) && !in_array($code, $missingApps, true)) {
                $missingApps[] = $code;
            }
        }

        $allowed = $this->widgetCatalog->typesForTenant($tenantId);
        $registry = new DiyWidgetRegistry();
        $missingWidgets = [];
        $pageSummaries = [];

        foreach ($extracted['pages'] as $key => $page) {
            $types = [];
            foreach ($page['components'] as $c) {
                if (is_array($c) && isset($c['type'])) {
                    $types[] = (string) $c['type'];
                }
            }
            $types = array_values(array_unique($types));
            foreach ($types as $type) {
                if (!in_array($type, $allowed, true) && !in_array($type, $missingWidgets, true)) {
                    $missingWidgets[] = $type;
                }
            }
            try {
                $registry->validate($page['components'], $allowed);
            } catch (BusinessException $e) {
                // 允许预检列出缺失类型；其它结构错误阻断
                if (!str_contains($e->getMessage(), '类型不支持')) {
                    $blocking[] = "{$key}: " . $e->getMessage();
                }
            }
            $pageSummaries[] = [
                'platform'        => $page['platform'],
                'page_key'        => $page['page_key'],
                'title'           => $page['title'],
                'page_type'       => $page['page_type'],
                'component_count' => count($page['components']),
                'widget_types'    => $types,
            ];
        }

        if ($missingWidgets !== []) {
            $warnings[] = '存在未授权组件类型，需先安装对应应用';
        }

        return [
            'manifest'         => [
                'code'                => $manifest['code'] ?? '',
                'name'                => $manifest['name'] ?? '',
                'version'             => $manifest['version'] ?? '',
                'framework_saas_min'  => $min,
                'requires_apps'       => $manifest['requires_apps'] ?? [],
                'recommended_for_app' => $manifest['recommended_for_app'] ?? null,
                'platforms'           => $manifest['platforms'] ?? [],
                'pages'               => $manifest['pages'] ?? [],
            ],
            'mobile'           => [
                'theme_color'   => $mobile['theme_color'] ?? '',
                'theme_colors'  => $mobile['theme_colors'] ?? [],
                'tabbar_count'  => is_array($mobile['tabbar'] ?? null) ? count($mobile['tabbar']) : 0,
                'home_app_code' => $homeCode,
                'home_page'     => $mobile['home_page'] ?? '',
            ],
            'pages'            => $pageSummaries,
            'missing_apps'     => $missingApps,
            'missing_widgets'  => $missingWidgets,
            'blocking_errors'  => $blocking,
            'warnings'         => $warnings,
        ];
    }

    private function backupPageIfNeeded(string $pageKey, string $platform, int $createdBy): void
    {
        $row = $platform === 'uniapp'
            ? $this->repo->findByKey($pageKey)
            : $this->repo->findByKeyPlatform($pageKey, $platform);
        if ($row === null) {
            return;
        }
        $published = $row['components_published'] ?? [];
        $draft = $row['components_draft'] ?? [];
        $snap = is_array($published) && $published !== [] ? $published : (is_array($draft) ? $draft : []);
        if ($snap === []) {
            return;
        }
        $settings = is_array($row['page_settings'] ?? null) ? $row['page_settings'] : [];
        $this->repo->insertVersion((int) $row['id'], $snap, $settings, $createdBy, '皮肤包套用');
    }

    /**
     * @param array<int,mixed> $components
     * @param array<string,mixed> $settings
     */
    private function applyPcHomeDraft(array $components, array $settings, string $title, int $createdBy): void
    {
        $tid = $this->tenantId();
        $allowed = $this->widgetCatalog->typesForTenant($tid);
        $clean = (new DiyWidgetRegistry())->validate($components, $allowed);
        $now = date('Y-m-d H:i:s');
        $row = $this->repo->findByKeyPlatform('home', 'pc');
        if ($row === null) {
            $this->repo->create([
                'page_type'        => 'home',
                'page_key'         => 'home',
                'platform'         => 'pc',
                'title'            => $title !== '' ? $title : 'PC首页',
                'components_draft' => $clean,
                'page_settings'    => $settings,
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            return;
        }
        $this->backupPageIfNeeded('home', 'pc', $createdBy);
        $this->repo->update((int) $row['id'], [
            'components_draft' => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'page_settings'    => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'updated_at'       => $now,
        ]);
    }

    /**
     * @param array<string,array<string,mixed>> $pageFiles
     * @param array<string,mixed> $mobile
     * @return array{apps:array<int,string>,plugins:array<int,string>}
     */
    private function detectRequires(array $pageFiles, array $mobile): array
    {
        $apps = [];
        $home = (string) ($mobile['home_app_code'] ?? '');
        if ($home !== '') {
            $apps[] = $home;
        }
        foreach ((array) ($mobile['tabbar'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = (string) ($item['code'] ?? '');
            if ($code !== '' && !str_starts_with($code, '__')) {
                $apps[] = $code;
            }
        }

        $builtins = DiyWidgetRegistry::TYPES;
        $plugins = [];
        foreach ($pageFiles as $pf) {
            foreach ((array) ($pf['components'] ?? []) as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $type = (string) ($c['type'] ?? '');
                if ($type === '' || in_array($type, $builtins, true)) {
                    continue;
                }
                // 插件 widget type 约定：{pluginCode}.{widget} 或纯插件自定义；取首段作为 code 猜测
                if (str_contains($type, '.')) {
                    $plugins[] = explode('.', $type, 2)[0];
                }
            }
        }

        return [
            'apps'    => array_values(array_unique($apps)),
            'plugins' => array_values(array_unique($plugins)),
        ];
    }

    /** @param array<string,mixed> $row */
    private function pickComponents(array $row): array
    {
        $published = $row['components_published'] ?? [];
        if (is_array($published) && $published !== []) {
            return $published;
        }
        $draft = $row['components_draft'] ?? [];

        return is_array($draft) ? $draft : [];
    }

    /** @param array<string,true> $bag */
    private function collectUrls(mixed $node, array &$bag): void
    {
        if (is_string($node)) {
            if ($this->looksLikeAssetUrl($node)) {
                $bag[$node] = true;
            }

            return;
        }
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $v) {
            $this->collectUrls($v, $bag);
        }
    }

    private function looksLikeAssetUrl(string $s): bool
    {
        if ($s === '' || str_starts_with($s, SkinZip::ASSET_PREFIX)) {
            return false;
        }
        if (preg_match('#^https?://#i', $s)) {
            return (bool) preg_match('#\.(png|jpe?g|gif|webp|svg)(\?|#|$)#i', $s)
                || str_contains($s, '/storage/')
                || str_contains($s, '/uploads/');
        }
        if (str_starts_with($s, '/storage/') || str_starts_with($s, '/uploads/')) {
            return true;
        }

        return false;
    }

    /** @return array{name:string,path:string}|null */
    private function packAsset(string $url, string $dir): ?array
    {
        $local = $this->resolveLocalPath($url);
        $bin = null;
        $ext = 'png';
        if ($local !== null && is_file($local)) {
            $bin = @file_get_contents($local);
            $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION) ?: 'png');
        } elseif (preg_match('#^https?://#i', $url)) {
            $bin = $this->httpGet($url);
            if (preg_match('#\.(png|jpe?g|gif|webp|svg)#i', $url, $m)) {
                $ext = strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
            }
        }
        if ($bin === null || $bin === false || $bin === '') {
            return null;
        }
        $name = substr(hash('sha256', $url), 0, 16) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $bin) === false) {
            return null;
        }

        return ['name' => $name, 'path' => $path];
    }

    private function resolveLocalPath(string $url): ?string
    {
        $path = $url;
        if (preg_match('#^https?://[^/]+(/storage/.+)$#i', $url, $m)) {
            $path = $m[1];
        }
        if (str_starts_with($path, '/storage/')) {
            $rel = ltrim(substr($path, strlen('/storage/')), '/');
            $full = public_path() . 'storage' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            return is_file($full) ? $full : null;
        }
        if (str_starts_with($path, '/uploads/')) {
            $rel = ltrim($path, '/');
            $full = public_path() . 'storage' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (is_file($full)) {
                return $full;
            }
            $alt = public_path() . str_replace('/', DIRECTORY_SEPARATOR, $rel);

            return is_file($alt) ? $alt : null;
        }

        return null;
    }

    private function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create(['http' => ['timeout' => 8], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
            $bin = @file_get_contents($url, false, $ctx);

            return is_string($bin) ? $bin : null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $bin = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($bin === false || $code >= 400) {
            return null;
        }

        return is_string($bin) ? $bin : null;
    }

    /** @param array<string,string> $map */
    private function rewriteUrls(mixed $node, array $map): mixed
    {
        if ($map === []) {
            return $node;
        }
        if (is_string($node)) {
            return $map[$node] ?? $node;
        }
        if (!is_array($node)) {
            return $node;
        }
        $out = [];
        foreach ($node as $k => $v) {
            $out[$k] = $this->rewriteUrls($v, $map);
        }

        return $out;
    }

    /**
     * @return array<string,string> placeholder|filename => public url
     */
    private function uploadAssets(string $assetsDir, int $tenantId, int $uploadBy): array
    {
        $map = [];
        if (!is_dir($assetsDir)) {
            return $map;
        }
        $files = glob($assetsDir . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $name = basename($file);
            $size = filesize($file) ?: 0;
            if ($size > 0) {
                $this->tenantQuotaService->assertCanStore($size);
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'png');
            $remote = 'uploads/images/' . date('Ymd') . '/' . uniqid('skin_', true) . '.' . $ext;
            $storage = StorageManager::disk();
            $driver = $storage->getDriver();
            if ($driver === 'local') {
                $abs = public_path() . 'storage' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $remote);
                $dir = dirname($abs);
                if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                    throw new BusinessException('无法写入本地存储', 500);
                }
                if (!@copy($file, $abs)) {
                    throw new BusinessException('资源写入失败：' . $name, 500);
                }
                $domain = '';
                try {
                    $domain = (string) request()->domain();
                } catch (\Throwable) {
                    $domain = '';
                }
                $url = ($domain !== '' ? $domain : '') . '/storage/' . $remote;
            } else {
                $url = $storage->upload($file, $remote);
            }
            $this->fileService->recordFile([
                'name'      => $name,
                'path'      => $remote,
                'url'       => $url,
                'mime_type' => $this->mimeByExt($ext),
                'extension' => $ext,
                'size'      => $size,
                'group'     => '皮肤包',
                'upload_by' => $uploadBy,
                'storage'   => $driver,
            ]);
            $map[SkinZip::ASSET_PREFIX . $name] = $url;
            $map[$name] = $url;
        }

        return $map;
    }

    private function mimeByExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    private function nextSkinKey(string $sourceKey): string
    {
        $base = rtrim(mb_substr($sourceKey, 0, 64 - 7), '-');
        for ($i = 1; $i <= 99; $i++) {
            $key = $base . '-skin' . ($i === 1 ? '' : (string) $i);
            if (!$this->repo->existsKey($key)) {
                return $key;
            }
        }
        throw new BusinessException('自定义页冲突过多，请先清理', 422);
    }

    private function sanitizeCode(string $code): string
    {
        $code = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $code) ?? '');
        $code = trim($code, '-');
        if (strlen($code) < 2) {
            $code = 'skin-' . date('YmdHis');
        }
        if (strlen($code) > 64) {
            $code = substr($code, 0, 64);
            $code = rtrim($code, '-');
        }
        if (!preg_match('/^[a-z][a-z0-9-]{1,62}[a-z0-9]$/', $code)) {
            $code = 'skin-' . date('YmdHis');
        }

        return $code;
    }

    private function tenantId(): int
    {
        $tid = TenantContext::current()?->id();
        if ($tid === null || $tid <= 0) {
            throw new BusinessException('缺少租户上下文', 400);
        }

        return $tid;
    }

    private function importCacheKey(int $tenantId, string $token): string
    {
        return "skin_import:{$tenantId}:{$token}";
    }
}
