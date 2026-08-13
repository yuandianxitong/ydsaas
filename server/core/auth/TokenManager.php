<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Cache;
use think\facade\Config;
use core\exception\AuthException;

class TokenManager
{
    /** @var array<string, TokenManager>  scope → manager 实例缓存 */
    private static array $instances = [];

    private string $scope;
    private string $key;
    private string $algorithm;
    private int $expire;
    private string $issuer;

    private function __construct(string $scope)
    {
        $this->scope = $scope;
        $config = Config::get('auth.jwt', []);
        $scopeConfig = $config[$scope] ?? null;

        if (!is_array($scopeConfig)) {
            throw new \InvalidArgumentException(lang('messages.jwt_scope_not_configured', ['scope' => $scope]));
        }

        $this->key = (string) ($scopeConfig['key'] ?? '');
        if (empty($this->key)) {
            throw new \RuntimeException(lang('messages.jwt_key_not_configured', [
                'scope'       => $scope,
                'scope_upper' => strtoupper($scope),
            ]));
        }
        if (strlen($this->key) < 32) {
            \think\facade\Log::warning(lang('messages.jwt_key_too_short', ['scope' => $scope]));
        }

        $this->algorithm = (string) ($config['algorithm'] ?? 'HS256');
        $this->expire = (int) ($config['expire'] ?? 7200);
        $this->issuer = (string) ($scopeConfig['issuer'] ?? "yd-admin-{$scope}");
    }

    /** 工厂方法：获取指定 scope 的 TokenManager 实例 */
    public static function scope(string $scope): self
    {
        if ($scope !== 'platform' && $scope !== 'tenant') {
            throw new \InvalidArgumentException("Unknown JWT scope: {$scope} (must be 'platform' or 'tenant')");
        }
        return self::$instances[$scope] ??= new self($scope);
    }

    /** 测试钩子：清空实例缓存（让 setUp 中的 Config::set 生效） */
    public static function flushInstances(): void
    {
        self::$instances = [];
    }

    /**
     * 生成Token
     */
    public function generate(array $payload): string
    {
        $now = time();
        $data = [
            'iss'   => $this->issuer,
            'iat'   => $now,
            'exp'   => $now + $this->expire,
            'jti'   => bin2hex(random_bytes(8)),
            'scope' => $this->scope,
            'data'  => $payload,
        ];
        return JWT::encode($data, $this->key, $this->algorithm);
    }

    /**
     * 验证Token
     */
    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, $this->algorithm));
            $payload = (array) $decoded;
        } catch (\Exception $e) {
            throw new AuthException(lang('auth.token_invalid') . ': ' . $e->getMessage());
        }

        // 严格校验 scope，防止跨 scope 用 token
        $tokenScope = $payload['scope'] ?? '';
        if ($tokenScope !== $this->scope) {
            throw new AuthException(lang('auth.token_invalid') . ': scope mismatch');
        }

        // 严格校验 issuer
        if (($payload['iss'] ?? '') !== $this->issuer) {
            throw new AuthException(lang('auth.token_invalid') . ': issuer mismatch');
        }

        if ($this->isBlacklisted($token)) {
            throw new AuthException(lang('auth.token_expired'));
        }

        return (array) $payload['data'];
    }

    /**
     * 刷新Token
     */
    public function refresh(string $token): string
    {
        $payload = $this->verify($token);
        $this->blacklist($token);
        return $this->generate($payload);
    }

    /**
     * 将Token加入黑名单
     */
    public function blacklist(string $token): bool
    {
        $key = "blacklist_token:{$this->scope}:" . md5($token);
        return Cache::set($key, 1, $this->expire);
    }

    /**
     * 检查Token是否在黑名单中
     */
    public function isBlacklisted(string $token): bool
    {
        $key = "blacklist_token:{$this->scope}:" . md5($token);
        return Cache::has($key);
    }

    /**
     * 从请求头获取Token
     */
    public function getTokenFromHeader(): ?string
    {
        $header = request()->header('Authorization', '');
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    /**
     * 从 token 中解析 user_id（便捷 helper）
     */
    public function getUserId(string $token): ?int
    {
        $payload = $this->verify($token);
        return isset($payload['user_id']) ? (int) $payload['user_id'] : null;
    }
}
