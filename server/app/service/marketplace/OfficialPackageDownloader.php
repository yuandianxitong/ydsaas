<?php

declare(strict_types=1);

namespace app\service\marketplace;

use core\base\Service;
use core\exception\BusinessException;

class OfficialPackageDownloader extends Service
{
    protected OfficialMarketplaceClient $client;
    protected PackageSignatureVerifier $verifier;

    public function download(string $url, string $token, string $siteBaseUrl): array
    {
        $this->assertSameHost($url, $siteBaseUrl);

        $dir = app()->getRuntimePath() . 'marketplace/tmp/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . bin2hex(random_bytes(8)) . '.zip';

        try {
            $resp   = $this->client->streamPackage($url, $token, $path);
            $sha    = (string) ($resp['X-Package-SHA256'] ?? '');
            $sigB64 = (string) ($resp['X-Signature']      ?? '');
            $keyId  = (string) ($resp['X-Key-Id']         ?? '');

            if ($sha === '' || $sigB64 === '' || $keyId === '') {
                throw new BusinessException('响应头缺签名信息', 422);
            }

            $localSha = hash_file('sha256', $path);
            if (!hash_equals(strtolower($sha), strtolower($localSha))) {
                throw new BusinessException('SHA256 不匹配', 422);
            }

            // 对 sha256 hex 摘要验签（Site 签的就是该摘要），而非整包字节
            $this->verifier->verify($localSha, $sigB64, $keyId, $siteBaseUrl);

            return ['path' => $path, 'sha256' => $localSha, 'key_id' => $keyId];
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }

    private function assertSameHost(string $downloadUrl, string $siteBaseUrl): void
    {
        $dlHost   = parse_url($downloadUrl, PHP_URL_HOST);
        $siteHost = parse_url($siteBaseUrl, PHP_URL_HOST);
        if (!$dlHost || !$siteHost || strcasecmp($dlHost, $siteHost) !== 0) {
            throw new BusinessException('下载 URL host 与 site_base_url 不匹配 (SSRF guard)', 422);
        }
    }
}
