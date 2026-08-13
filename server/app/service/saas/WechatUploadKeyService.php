<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\service\saas;

use app\repository\saas\TenantMobileConfigRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\runtime\RuntimePaths;
use core\utils\Encryption;
use think\facade\App;

/**
 * 租户微信小程序上传私钥（.key）管理。
 *
 *   - 写入：明文 → AES-256-CBC 加密 → tenant_mobile_configs.wechat_upload_key_ciphertext
 *   - 读取（仅在 miniprogram-ci 调用前临时落盘）：解密 → 写 runtime 临时文件 →
 *           返回路径供 Process 使用 → 调用方用完立即 dropTemp() 删除
 *
 * 密钥来源：core\utils\Encryption（沿用 APP_KEY）。
 */
final class WechatUploadKeyService extends Service
{
    protected TenantMobileConfigRepository $configRepository;

    /**
     * 写入加密的私钥。空字符串清除。
     */
    public function save(int $tenantId, string $plaintext): void
    {
        if ($plaintext === '') {
            $this->configRepository->upsert($tenantId, ['wechat_upload_key_ciphertext' => null]);
            return;
        }
        if (strlen($plaintext) > 32_000) {
            throw new BusinessException('上传私钥过大（>32KB），疑似选错文件', 422);
        }
        // 简单格式守卫：微信上传私钥是 PEM 格式
        if (!str_contains($plaintext, 'BEGIN PRIVATE KEY') && !str_contains($plaintext, 'BEGIN RSA PRIVATE KEY')) {
            throw new BusinessException('上传私钥文件格式不正确（应为 PEM 格式）', 422);
        }

        $ciphertext = Encryption::encrypt($plaintext);
        $this->configRepository->upsert($tenantId, [
            'wechat_upload_key_ciphertext' => $ciphertext,
        ]);
    }

    public function hasKey(int $tenantId): bool
    {
        $row = $this->configRepository->findByTenantId($tenantId);
        return !empty($row['wechat_upload_key_ciphertext'] ?? null);
    }

    /**
     * 解密并写入临时文件，返回文件路径。
     * 调用方 MUST 在用完立即 dropTemp() 删除。
     */
    public function exportToTempFile(int $tenantId): string
    {
        $row = $this->configRepository->findByTenantId($tenantId);
        $ciphertext = (string) ($row['wechat_upload_key_ciphertext'] ?? '');
        if ($ciphertext === '') {
            throw new BusinessException('该租户未上传小程序私钥', 422);
        }
        $plaintext = Encryption::decrypt($ciphertext);
        if ($plaintext === false || $plaintext === '') {
            throw new BusinessException('私钥解密失败', 500);
        }

        try {
            $dir = RuntimePaths::ensureKeysDir(App::getRootPath());
        } catch (\RuntimeException $e) {
            throw new BusinessException($e->getMessage(), 500);
        }
        $path = $dir . '/' . $tenantId . '-' . bin2hex(random_bytes(8)) . '.key';

        // 关键：先用 0177 umask 让 fopen 创建文件即 0600，避免 file_put_contents +
        // 事后 chmod 留下「短暂 0644 期间同主机其它进程可读」时间窗。
        $prevUmask = umask(0177);
        try {
            $fh = fopen($path, 'wb');
            if ($fh === false) {
                throw new BusinessException("failed to create {$path}", 500);
            }
            try {
                if (fwrite($fh, $plaintext) !== strlen($plaintext)) {
                    throw new BusinessException("failed to fully write {$path}", 500);
                }
            } finally {
                fclose($fh);
            }
            // 双保险：明确 chmod 0600（umask 在某些 FUSE 文件系统上不生效）
            @chmod($path, 0600);
        } finally {
            umask($prevUmask);
        }
        return $path;
    }

    public function dropTemp(string $path): void
    {
        if (is_file($path)) {
            // 覆盖一次再删，降低残留风险
            $size = filesize($path) ?: 0;
            if ($size > 0) {
                file_put_contents($path, str_repeat("\0", $size));
            }
            @unlink($path);
        }
    }
}
