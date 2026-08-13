<?php

declare(strict_types=1);

namespace app\service\marketplace;

use core\exception\BusinessException;

class TokenCipher
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct(?string $hexKey = null)
    {
        // 显式传入的 key 立即校验（调用方自己的责任）；未传入时延迟到实际 encrypt/decrypt
        // 再从 env 读取并校验。这样容器自动注入（$hexKey=null）阶段不会抛异常——否则会被
        // core\base\Service 的 DI 吞成 "Typed property $cipher must not be accessed before
        // initialization" 的天书报错，把「密钥未配置」的真实原因彻底掩盖。
        if ($hexKey !== null) {
            $this->key = $this->normalizeKey($hexKey);
        }
    }

    public function encrypt(string $plain): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new BusinessException('加密失败', 500);
        }
        return base64_encode($iv . $tag . $ct);
    }

    public function decrypt(string $cipherB64): string
    {
        $raw = base64_decode($cipherB64, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new BusinessException('密文损坏', 500);
        }
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $pt  = openssl_decrypt($ct, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new BusinessException('解密失败 (key 不匹配或数据被改)', 500);
        }
        return $pt;
    }

    /**
     * 延迟解析加密密钥：优先用构造时显式传入的 key，否则从 env 读取。
     * 未配置时抛出可操作报错，指明去 .env 的 [SAAS] 段补 MARKETPLACE_ENCRYPTION_KEY。
     */
    private function key(): string
    {
        if (!isset($this->key)) {
            $this->key = $this->normalizeKey((string) env('SAAS_MARKETPLACE_ENCRYPTION_KEY', ''));
        }
        return $this->key;
    }

    private function normalizeKey(string $hexKey): string
    {
        if (!preg_match('/^[0-9a-fA-F]{64}$/', $hexKey)) {
            throw new BusinessException(
                '应用市场加密密钥未配置或格式不正确：请在 .env 的 [SAAS] 段设置 MARKETPLACE_ENCRYPTION_KEY，'
                . '需为 64 位十六进制字符串（用 openssl rand -hex 32 生成），修改后执行 php think clear',
                500
            );
        }
        return hex2bin($hexKey);
    }
}
