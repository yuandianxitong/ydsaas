<?php

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplacePublicKeyRepository;
use core\base\Service;
use core\exception\BusinessException;

class PackageSignatureVerifier extends Service
{
    protected MarketplacePublicKeyRepository $repo;
    protected OfficialMarketplaceClient $client;

    /**
     * 验签目标是「包的 sha256 hex 摘要」，而非整包字节。
     * Site 端 SigningService::sign() 对 hash_file('sha256', pkg) 得到的 64 位 hex 串签名，
     * 调用方（OfficialPackageDownloader）已先行校验本地文件 sha256 与响应头一致，
     * 故此处对该摘要验签即可证明包来自官方且未被篡改。
     */
    public function verify(string $sha256Hex, string $signatureB64, string $keyId, string $siteBaseUrl): void
    {
        if ($keyId === '' || $signatureB64 === '') {
            throw new BusinessException('缺少签名信息', 422);
        }
        $pem = $this->getOrFetchPem($keyId, $siteBaseUrl);
        $sig = base64_decode($signatureB64, true);
        if ($sig === false) {
            throw new BusinessException('签名 base64 非法', 422);
        }
        $ok = openssl_verify($sha256Hex, $sig, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new BusinessException('签名校验失败', 422);
        }
    }

    public function getOrFetchPem(string $keyId, string $siteBaseUrl): string
    {
        $cached = $this->repo->getValidPem($keyId);
        if ($cached !== null) {
            return $cached;
        }
        $pem = $this->client->fetchPublicKey($siteBaseUrl, $keyId);
        if ($pem === '') {
            throw new BusinessException('Site 未返回公钥 ' . $keyId, 502);
        }
        $ttl = (int) config('saas.marketplace.public_key_ttl', 7 * 24 * 3600);
        $this->repo->store($keyId, $pem, $ttl);
        return $pem;
    }
}
