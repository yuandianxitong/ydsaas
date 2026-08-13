<?php

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceConnectionRepository;
use app\service\diy\SkinPackService;
use core\base\Service;
use core\exception\BusinessException;

/**
 * 从官方 Site 下载主题皮肤包并套用到当前租户（SkinPackService）。
 */
class MarketplaceThemeInstallService extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected OfficialMarketplaceClient $client;
    protected OfficialPackageDownloader $downloader;
    protected InstanceRegistrationService $registration;
    protected SkinPackService $skinPackService;

    /**
     * @return array<string,mixed> preview 或 apply 结果
     */
    public function install(string $themeCode, ?string $version = null, bool $autoApply = true, int $createdBy = 0): array
    {
        $themeCode = trim($themeCode);
        if ($themeCode === '') {
            throw new BusinessException('主题编码不能为空', 422);
        }

        $conn = $this->connRepo->findActive();
        if (!$conn) {
            throw new BusinessException('未绑定官方市场，请先在平台端连接 Site', 422);
        }
        $base = rtrim((string) $conn['site_base_url'], '/');
        $token = $this->registration->decryptToken($conn);

        $detail = $this->client->getPublicTheme($base, $themeCode);
        if ($detail === []) {
            throw new BusinessException('主题不存在或未上架', 404);
        }
        $ver = $version ?: (string) ($detail['latest_version']['version'] ?? '');
        if ($ver === '') {
            throw new BusinessException('主题无已发布版本', 422);
        }

        $dlInfo = $this->client->requestThemeDownloadToken($base, $token, $themeCode, $ver);
        $url = (string) ($dlInfo['download_url'] ?? '');
        if ($url === '') {
            throw new BusinessException('Site 未返回 download_url', 502);
        }
        if (!str_starts_with($url, 'http')) {
            $url = $base . $url;
        }

        $dl = $this->downloader->download($url, $token, $base);
        try {
            $preview = $this->skinPackService->importPreview($dl['path']);
            if ($autoApply && ($preview['ok'] ?? false)) {
                $applied = $this->skinPackService->apply((string) $preview['token'], $createdBy);

                return [
                    'preview' => $preview,
                    'applied' => $applied,
                    'theme_code' => $themeCode,
                    'version' => $ver,
                ];
            }

            return [
                'preview' => $preview,
                'applied' => null,
                'theme_code' => $themeCode,
                'version' => $ver,
            ];
        } finally {
            @unlink($dl['path']);
        }
    }

    /**
     * 列出 Site 已上架主题（公开接口，不强制已绑定；已绑定时用连接的 site_base_url）。
     *
     * @return array{list:array<int,mixed>,pagination?:array<string,mixed>}
     */
    public function listRemote(array $params = []): array
    {
        $base = $this->resolveSiteBase();
        return $this->client->listPublicThemes($base, $params);
    }

    private function resolveSiteBase(): string
    {
        $conn = $this->connRepo->findActive();
        if ($conn && !empty($conn['site_base_url'])) {
            return rtrim((string) $conn['site_base_url'], '/');
        }
        $fallback = (string) config('saas.marketplace.default_site_base_url', '');
        if ($fallback === '') {
            throw new BusinessException('未配置官方市场地址', 422);
        }

        return rtrim($fallback, '/');
    }
}
