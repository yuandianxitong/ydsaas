<?php

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceConnectionRepository;
use app\service\saas\PlatformConfigService;
use core\base\Service;
use core\exception\BusinessException;
use core\license\LicenseGuard;
use think\facade\Cache;
use think\facade\Event;

class InstanceRegistrationService extends Service
{
    protected MarketplaceConnectionRepository $repo;
    protected OfficialMarketplaceClient $client;
    protected TokenCipher $cipher;
    protected PlatformConfigService $platformConfig;

    /**
     * 发起 OAuth 授权。Site 地址与实例身份均由服务端决定，不接受前端传入：
     *   - site_base_url 取 config('saas.marketplace.default_site_base_url')
     *   - instance_uuid 取服务端持久化的稳定值（重连/换绑复用同一实例身份）
     *   - instance_name 由 platform_site_name + 当前域名 自动派生
     *
     * @param array $intent 可选安装上下文，写入 state 缓存供 exchange() 兑换后自动 resolve，
     *                      形如 ['intent' => 'install', 'app_code' => 'crm-core'] 或 ['intent' => 'connect']
     */
    public function initiate(string $callbackUrl, array $intent = []): array
    {
        // 平台产品授权守卫：
        // - 已撤销/过期：始终拦截
        // - 未激活：仅当 license.enforce_marketplace=true 时拦截（默认 false，兼容存量）
        $eval = LicenseGuard::status();
        $status = (string) ($eval['status'] ?? 'inactive');
        $enforce = (bool) config('license.enforce_marketplace', false);
        $blocked = in_array($status, ['revoked', 'expired'], true)
            || ($enforce && $status === 'inactive')
            || ($enforce && empty($eval['pro_enabled']));
        if ($blocked) {
            throw new BusinessException(
                '平台商业授权未生效，无法连接官方应用市场。请先在「系统 → 产品授权」激活授权码。' .
                '（当前状态：' . $status . '）',
                403
            );
        }

        // 两套基址（去尾斜杠，避免 http://host/pc/ + '/connect/saas' 拼出双斜杠让前端路由 404）：
        //   apiBaseUrl —— 开放 API（服务器到服务器，存进 connection 供后续所有 API 调用）
        //   webBaseUrl —— 浏览器授权页 /connect/saas；留空则与 apiBaseUrl 相同
        // 本地 Site 前端在 /pc、开放 API 在根，二者不同，必须分开，否则授权后 exchange 会把
        // /api/open/... 打到 /pc/api/open/...（被前端 SPA 接走返回 HTML）→「Site API 返回非 JSON」。
        $apiBaseUrl = rtrim((string) config('saas.marketplace.default_site_base_url', 'https://www.dev007.cn'), '/');
        $webBaseUrl = rtrim((string) (config('saas.marketplace.web_base_url') ?: $apiBaseUrl), '/');
        $this->assertHttpsUrl($apiBaseUrl);
        $this->assertHttpsUrl($webBaseUrl);
        $this->assertCallbackUrl($callbackUrl);

        $instanceUuid = $this->installationUuid();
        $instanceName = $this->deriveInstanceName();
        $verifier     = $this->base64url(random_bytes(64));
        $challenge    = $this->base64url(hash('sha256', $verifier, true));
        $state        = $this->base64url(random_bytes(32));

        $payload = [
            'verifier'      => $verifier,
            'instance_uuid' => $instanceUuid,
            'instance_name' => $instanceName,
            'site_base_url' => $apiBaseUrl,
        ];
        if (!empty($intent)) {
            $payload['intent'] = $intent;
        }
        Cache::set('oauth_pkce:' . $state, json_encode($payload), 600);

        $url = $webBaseUrl . '/connect/saas?'
            . http_build_query([
                'instance_uuid'         => $instanceUuid,
                'instance_name'         => $instanceName,
                'callback_url'          => $callbackUrl,
                'state'                 => $state,
                'code_challenge'        => $challenge,
                'code_challenge_method' => 'S256',
            ]);
        return ['authorize_url' => $url, 'state' => $state];
    }

    public function exchange(string $state, string $code): array
    {
        $raw = Cache::get('oauth_pkce:' . $state);
        if (!$raw) {
            throw new BusinessException('授权请求已过期, 请重新发起', 400);
        }
        $ctx = json_decode($raw, true);

        $exchangeResp = $this->client->exchangeCode($ctx['site_base_url'], [
            'auth_code'     => $code,
            'code_verifier' => $ctx['verifier'],
            'instance_uuid' => $ctx['instance_uuid'],
        ]);
        $token     = (string) ($exchangeResp['access_token'] ?? '');
        $boundUser = $exchangeResp['bound_user'] ?? [];

        if ($token === '') {
            throw new BusinessException('Site 未返回 token', 502);
        }

        $now   = date('Y-m-d H:i:s');
        $attrs = [
            'instance_name'            => $ctx['instance_name'],
            'site_base_url'            => $ctx['site_base_url'],
            'encrypted_instance_token' => $this->cipher->encrypt($token),
            'token_prefix'             => substr($token, 0, 11),
            'bound_user_email'         => (string) ($boundUser['email'] ?? ''),
            'bound_user_name'          => (string) ($boundUser['name']  ?? ''),
            'status'                   => 'active',
            'token_rotated_at'         => $now,
            'token_expires_at'         => null, // 后续 rotate 写入
            'updated_at'               => $now,
        ];

        // installation_uuid 现在是服务端稳定值：重连 / 换绑会复用同一 uuid。
        // marketplace_connections 上有 uk_instance_uuid 唯一键，因此必须原地复活
        // 已存在（含已 revoke 软删）的行，不能再无脑 insert，否则撞唯一键。
        $existing = $this->repo->findByUuid($ctx['instance_uuid']);
        if ($existing) {
            $attrs['deleted_at'] = null;
            $attrs['last_error'] = null;
            $this->repo->update((int) $existing['id'], $attrs);
            $row = $this->repo->find((int) $existing['id']);
        } else {
            $attrs['instance_uuid'] = $ctx['instance_uuid'];
            $attrs['created_at']    = $now;
            $row = $this->repo->create($attrs);
        }

        try {
            $this->client->heartbeat($ctx['site_base_url'], $token, (string) config('version.version'));
            $this->repo->markHeartbeat((int) $row['id']);
        } catch (\Throwable $e) {
            $this->repo->markError((int) $row['id'], '初次 heartbeat 失败: ' . $e->getMessage());
        }

        Cache::delete('oauth_pkce:' . $state);
        Event::trigger('marketplace.connection.bound', $row);

        $shaped = $this->shape($row);
        // 把 state 缓存里的安装意图透传给调用方（Controller），由其在建连后自动 sync + resolve。
        // 用下划线前缀键标记为内部字段，Controller 消费后会剔除，不会下发前端。
        $shaped['_intent'] = $ctx['intent'] ?? null;
        return $shaped;
    }

    public function decryptToken(array $connection): string
    {
        return $this->cipher->decrypt((string) $connection['encrypted_instance_token']);
    }

    private function shape(array $row): array
    {
        return [
            'id'                => (int) $row['id'],
            'instance_uuid'     => $row['instance_uuid'],
            'instance_name'     => $row['instance_name'],
            'site_base_url'     => $row['site_base_url'],
            'token_prefix'      => $row['token_prefix'],
            'bound_user_email'  => $row['bound_user_email']  ?? null,
            'bound_user_name'   => $row['bound_user_name']   ?? null,
            'status'            => $row['status'],
            'last_heartbeat_at' => $row['last_heartbeat_at'] ?? null,
            'last_sync_at'      => $row['last_sync_at']      ?? null,
        ];
    }

    /**
     * site_base_url 校验:
     *   - https 任意 host 允许
     *   - http 仅允许 localhost / 127.0.0.1 / 0.0.0.0 / ::1 (本地开发自托管 Site)
     */
    private function assertHttpsUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new BusinessException('URL 非法', 422);
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host   = (string) parse_url($url, PHP_URL_HOST);
        if ($scheme === 'https') {
            return;
        }
        if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return;
        }
        throw new BusinessException('site_base_url 必须 https (本地开发可用 http://localhost)', 422);
    }

    /**
     * 校验 callback_url:
     *   - 必须是合法 URL
     *   - https 任意 host 允许
     *   - http 仅允许 localhost / 127.0.0.1 / *.local 等 loopback (本地开发)
     */
    private function assertCallbackUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new BusinessException('callback_url 非法', 422);
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host   = (string) parse_url($url, PHP_URL_HOST);
        if ($scheme === 'https') {
            return;
        }
        if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return;
        }
        throw new BusinessException('callback_url 必须 https (本地开发可用 http://localhost)', 422);
    }

    /**
     * 服务端持久化的稳定 installation uuid（首次生成后写入 system_configs，之后复用）。
     * 重连 / 换绑都复用同一实例身份，避免 Site 侧产生大量重复实例。
     */
    private function installationUuid(): string
    {
        return $this->platformConfig->getOrCreateValue(
            'platform_marketplace_installation_uuid',
            fn () => $this->uuidV4()
        );
    }

    /**
     * 实例名自动派生：平台站点名 + 当前域名，普通管理员无需填写。
     */
    private function deriveInstanceName(): string
    {
        $siteName = (string) $this->platformConfig->getConfigValue('platform_site_name', '');
        if ($siteName === '') {
            $siteName = '平台管理后台';
        }
        $host = '';
        try {
            $host = (string) request()->host();
        } catch (\Throwable) {
            $host = '';
        }
        $name = $host !== '' ? sprintf('%s (%s)', $siteName, $host) : $siteName;
        return mb_substr($name, 0, 120);
    }

    private function uuidV4(): string
    {
        $b    = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
