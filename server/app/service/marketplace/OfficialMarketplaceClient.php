<?php

declare(strict_types=1);

namespace app\service\marketplace;

use core\base\Service;
use core\exception\BusinessException;

class OfficialMarketplaceClient extends Service
{
    /** @var callable|null */
    private $transport = null;

    public function setTransport(?callable $t): void
    {
        $this->transport = $t;
    }

    public function heartbeat(string $base, string $token, string $version): array
    {
        return $this->call($base . '/api/open/instances/heartbeat', 'POST', $token, ['framework_saas_version' => $version]);
    }

    public function entitlements(string $base, string $token): array
    {
        $r = $this->call($base . '/api/open/instances/entitlements', 'GET', $token);
        return $r['data'] ?? [];
    }

    public function requestDownloadToken(string $base, string $token, string $appCode, string $version): array
    {
        $url = $base . '/api/open/market/apps/' . rawurlencode($appCode) . '/versions/' . rawurlencode($version) . '/download-token';
        return $this->call($url, 'POST', $token)['data'] ?? [];
    }

    /** 主题皮肤包下载令牌（免费或已购主题）。 */
    public function requestThemeDownloadToken(string $base, string $token, string $themeCode, string $version): array
    {
        $url = $base . '/api/open/market/themes/' . rawurlencode($themeCode) . '/versions/' . rawurlencode($version) . '/download-token';
        return $this->call($url, 'POST', $token)['data'] ?? [];
    }

    /**
     * 公开主题列表（commerce 接口，无需 token）。
     * @return array{list:array<int,mixed>,pagination?:array<string,mixed>}
     */
    public function listPublicThemes(string $base, array $params = []): array
    {
        $qs = http_build_query(array_filter([
            'page' => $params['page'] ?? 1,
            'limit' => $params['limit'] ?? 24,
            'keyword' => $params['keyword'] ?? null,
            'price_type' => $params['price_type'] ?? null,
            'recommended_for_app' => $params['recommended_for_app'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));
        $r = $this->call($base . '/api/commerce/market/themes?' . $qs, 'GET', null);
        $data = $r['data'] ?? [];

        return is_array($data) ? $data : ['list' => []];
    }

    /** 公开主题详情。 */
    public function getPublicTheme(string $base, string $code): array
    {
        $r = $this->call($base . '/api/commerce/market/themes/' . rawurlencode($code), 'GET', null);

        return is_array($r['data'] ?? null) ? $r['data'] : [];
    }

    public function fetchPublicKey(string $base, string $keyId): string
    {
        $r = $this->call($base . '/api/open/market/public-key/' . rawurlencode($keyId), 'GET', null);
        // Site 的公钥接口返回字段为 public_key（见 Site PublicKeyController::show）；
        // 兼容历史/测试里使用的 pem 字段名。
        return (string) ($r['data']['public_key'] ?? $r['data']['pem'] ?? '');
    }

    public function exchangeCode(string $base, array $payload): array
    {
        return $this->call($base . '/api/open/instances/exchange-code', 'POST', null, $payload)['data'] ?? [];
    }

    /**
     * 用旧 token 轮换新 token。Site v1.9.0 端点。
     * 返回 {access_token, expires_at, instance_uuid}。
     */
    public function rotateToken(string $base, string $oldToken): array
    {
        $r = $this->call($base . '/api/open/instances/tokens/rotate', 'POST', $oldToken);
        $data = $r['data'] ?? [];
        if (empty($data['access_token'])) {
            throw new BusinessException('Site rotate-token 响应缺 access_token', 502);
        }
        return $data;
    }

    /**
     * 上报安装/升级/卸载/回滚匿名审计事件到 Site v1.9.0 端点。
     * fire-and-forget 语义：成功返 204，失败也只是上报丢失，调用方需 swallow exception。
     * 短超时 (默认 3s) 避免阻塞主流程。
     */
    public function postAudit(string $base, string $token, array $payload, int $timeoutSeconds = 3): void
    {
        $opts = [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'json'    => $payload,
            'timeout' => $timeoutSeconds,
        ];
        $resp = $this->transport
            ? ($this->transport)($base . '/api/open/instances/audit', $opts)
            : $this->curl($base . '/api/open/instances/audit', $opts);
        // 204 No Content 是 Site 端正常响应；其他 2xx 也接受；非 2xx 视作失败但不抛（调用方处理）
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new BusinessException('Site audit 接收失败 HTTP ' . $resp['status'], 502);
        }
    }

    /**
     * 获取应用某版本元数据（含 changelog）。复用 Site B-1 既有公开端点。
     */
    public function getVersion(string $base, string $token, string $appCode, string $version): array
    {
        $url = $base . '/api/open/market/apps/' . rawurlencode($appCode) . '/versions/' . rawurlencode($version);
        $r = $this->call($url, 'GET', $token);
        return $r['data'] ?? [];
    }

    /**
     * 获取 Site 公开应用分类
     */
    public function publicCategories(string $base): array
    {
        $r = $this->call($base . '/api/open/market/categories', 'GET', null);
        $payload = $r['data'] ?? [];
        return $payload['list'] ?? (is_array($payload) && array_is_list($payload) ? $payload : []);
    }

    /**
     * 获取 Site 公开应用 catalog 全量（不需要 token，未绑定 Site 也可浏览）。
     * Site 实际响应: {code, data: {list: [...], pagination: {...}}}，单页默认 20 条。
     *
     * 这里按 page/limit 逐页拉取并拼成完整列表 —— 让上层（CatalogController）能对
     * 全量结果做服务端分页，规避 Site 单页 20 条上限。终止条件优先用 Site 返回的
     * pagination（total/last_page），无 pagination 时退化为「本页不足 limit 即结束」，
     * 并以 MAX_CATALOG_PAGES 兜底防御死循环。
     */
    private const CATALOG_PAGE_SIZE  = 50;
    private const MAX_CATALOG_PAGES  = 100;

    public function publicCatalog(string $base, ?int $categoryId = null): array
    {
        $all   = [];
        $limit = self::CATALOG_PAGE_SIZE;

        for ($page = 1; $page <= self::MAX_CATALOG_PAGES; $page++) {
            $url = $base . '/api/open/market/apps?page=' . $page . '&limit=' . $limit;
            if ($categoryId !== null && $categoryId > 0) {
                $url .= '&category_id=' . $categoryId;
            }
            $r       = $this->call($url, 'GET', null);
            $payload = $r['data'] ?? [];
            // 兼容多种 shape: data.list / data.data / data (平铺)
            $list = $payload['list'] ?? $payload['data'] ?? (is_array($payload) && array_is_list($payload) ? $payload : []);
            if (!is_array($list) || $list === []) {
                break;
            }
            foreach ($list as $item) {
                $all[] = $item;
            }

            // 终止判定：优先信任 Site 的 pagination
            $pg = is_array($payload) ? ($payload['pagination'] ?? null) : null;
            if (is_array($pg)) {
                $total    = (int) ($pg['total'] ?? 0);
                $lastPage = (int) ($pg['last_page'] ?? 0);
                if (($total > 0 && count($all) >= $total) || ($lastPage > 0 && $page >= $lastPage)) {
                    break;
                }
                continue;
            }
            // 无 pagination：本页不足 limit 说明已到末页
            if (count($list) < $limit) {
                break;
            }
        }

        return $all;
    }

    public function streamPackage(string $url, string $token, string $destPath): array
    {
        if ($this->transport) {
            $resp = ($this->transport)($url, ['method' => 'GET', 'headers' => ['Authorization' => 'Bearer ' . $token], 'stream_to' => $destPath]);
            if ($resp['status'] < 200 || $resp['status'] >= 300) {
                throw new BusinessException('包下载失败 HTTP ' . $resp['status'], 502);
            }
            return $resp['headers'] ?? [];
        }
        return $this->curlStream($url, $token, $destPath);
    }

    private function call(string $url, string $method, ?string $token, ?array $json = null): array
    {
        $opts = ['method' => $method, 'headers' => []];
        if ($token !== null) {
            $opts['headers']['Authorization'] = 'Bearer ' . $token;
        }
        if ($json !== null) {
            $opts['json'] = $json;
            $opts['headers']['Content-Type'] = 'application/json';
        }

        $resp = $this->transport
            ? ($this->transport)($url, $opts)
            : $this->curl($url, $opts);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new BusinessException('Site API ' . $url . ' 返回 ' . $resp['status'], 502);
        }
        $decoded = json_decode($resp['body'], true);
        if (!is_array($decoded)) {
            throw new BusinessException('Site API 返回非 JSON', 502);
        }
        return $decoded;
    }

    private function curl(string $url, array $opts): array
    {
        $timeout = isset($opts['timeout']) && (int) $opts['timeout'] > 0
            ? (int) $opts['timeout']
            : (int) config('saas.marketplace.http_timeout', 15);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST  => $opts['method'],
        ]);
        $headers = [];
        foreach ($opts['headers'] ?? [] as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }
        if (!empty($opts['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new BusinessException('curl 失败: ' . $err, 502);
        }
        return ['status' => $status, 'body' => $body];
    }

    private function curlStream(string $url, string $token, string $destPath): array
    {
        $fp = fopen($destPath, 'wb');
        if (!$fp) {
            throw new BusinessException('打开临时文件失败', 500);
        }
        $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => (int) config('saas.marketplace.download_timeout', 120),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_HEADERFUNCTION => function ($_ch, $hdr) use (&$headers) {
                if (str_contains($hdr, ':')) {
                    [$k, $v] = explode(':', $hdr, 2);
                    $headers[trim($k)] = trim($v);
                }
                return strlen($hdr);
            },
        ]);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($status < 200 || $status >= 300) {
            @unlink($destPath);
            throw new BusinessException('包下载失败 HTTP ' . $status, 502);
        }
        // 返回「扁平」的响应头数组，与 transport 分支 `$resp['headers']` 的形状保持一致。
        // 调用方 OfficialPackageDownloader::download() 直接按 $resp['X-Package-SHA256'] 取值，
        // 若这里再包一层 ['headers'=>...] 会导致签名头恒为 null → 生产环境必报「响应头缺签名信息」。
        return [
            'X-Package-SHA256' => $headers['X-Package-SHA256'] ?? null,
            'X-Signature'      => $headers['X-Signature']      ?? null,
            'X-Key-Id'         => $headers['X-Key-Id']         ?? null,
        ];
    }
}
