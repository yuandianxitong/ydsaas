<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile\remote;

/**
 * RemoteBuildClient 的 curl 实现。
 *
 * 经 setTransport() 注入 fake 传输层做单测（镜像 OfficialMarketplaceClient idiom）。
 * transport 契约：fn(string $url, array $opts): array{status:int, body:string}
 *   opts: method / headers(关联数组) / multipart(['fields'=>[], 'file'=>['field','path']]) / stream_to / timeout
 */
final class CurlRemoteBuildClient implements RemoteBuildClient
{
    /** @var null|callable(string,array):array */
    private $transport = null;

    public function setTransport(?callable $t): void
    {
        $this->transport = $t;
    }

    public function createJob(string $base, string $token, string $workspaceZip, string $platform, int $tenantId, int $buildId, int $timeoutSec): array
    {
        $resp = $this->send(rtrim($base, '/') . '/api/mobile-builds', [
            'method'    => 'POST',
            'headers'   => ['Authorization' => 'Bearer ' . $token],
            'multipart' => [
                'fields' => ['platform' => $platform, 'tenant_id' => $tenantId, 'build_id' => $buildId],
                'file'   => ['field' => 'workspace', 'path' => $workspaceZip],
            ],
            'timeout'   => $timeoutSec,
        ]);
        $data = $this->decode($resp, 'createJob');
        return ['job_id' => (string) ($data['job_id'] ?? ''), 'status' => (string) ($data['status'] ?? '')];
    }

    public function getJob(string $base, string $token, string $jobId, int $timeoutSec): array
    {
        $resp = $this->send(rtrim($base, '/') . '/api/mobile-builds/' . rawurlencode($jobId), [
            'method'  => 'GET',
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => $timeoutSec,
        ]);
        $data = $this->decode($resp, 'getJob');
        return [
            'status'      => (string) ($data['status'] ?? ''),
            'artifact_url' => (string) ($data['artifact_url'] ?? ''),
            'log_excerpt' => (string) ($data['log_excerpt'] ?? ''),
        ];
    }

    public function downloadArtifact(string $base, string $token, string $jobId, string $destZipPath, int $timeoutSec): void
    {
        $resp = $this->send(rtrim($base, '/') . '/api/mobile-builds/' . rawurlencode($jobId) . '/artifact', [
            'method'    => 'GET',
            'headers'   => ['Authorization' => 'Bearer ' . $token],
            'stream_to' => $destZipPath,
            'timeout'   => $timeoutSec,
        ]);
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new \RuntimeException("[remote] artifact download failed (HTTP {$resp['status']})");
        }
    }

    /**
     * @param array{status:int, body:string} $resp
     * @return array<string, mixed> data 段
     */
    private function decode(array $resp, string $op): array
    {
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new \RuntimeException("[remote] {$op} HTTP {$resp['status']}: " . mb_substr($resp['body'], 0, 500));
        }
        $json = json_decode($resp['body'], true);
        if (!is_array($json) || (int) ($json['code'] ?? -1) !== 0) {
            throw new \RuntimeException("[remote] {$op} bad envelope: " . mb_substr($resp['body'], 0, 500));
        }
        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    /**
     * @param array<string, mixed> $opts
     * @return array{status:int, body:string}
     */
    private function send(string $url, array $opts): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, $opts);
        }
        return $this->curl($url, $opts);
    }

    /**
     * @param array<string, mixed> $opts
     * @return array{status:int, body:string}
     */
    private function curl(string $url, array $opts): array
    {
        $ch = curl_init($url);
        $timeout = (int) ($opts['timeout'] ?? 60);
        $headers = [];
        foreach (($opts['headers'] ?? []) as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }

        $common = [
            CURLOPT_CUSTOMREQUEST  => $opts['method'] ?? 'GET',
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if (isset($opts['stream_to'])) {
            $fp = fopen((string) $opts['stream_to'], 'wb');
            if ($fp === false) {
                throw new \RuntimeException('[remote] cannot open dest for download: ' . $opts['stream_to']);
            }
            $common[CURLOPT_FILE] = $fp;
        } else {
            $common[CURLOPT_RETURNTRANSFER] = true;
        }

        if (isset($opts['multipart'])) {
            $fields = $opts['multipart']['fields'] ?? [];
            $file   = $opts['multipart']['file'] ?? null;
            $post   = $fields;
            if (is_array($file)) {
                $post[$file['field']] = new \CURLFile($file['path'], 'application/zip', basename($file['path']));
            }
            $common[CURLOPT_POSTFIELDS] = $post; // 数组 → multipart/form-data
        }

        $common[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $common);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if (isset($fp) && is_resource($fp)) {
            fclose($fp);
        }
        if ($body === false) {
            throw new \RuntimeException('[remote] curl error: ' . ($err !== '' ? $err : "no response (HTTP {$status})"));
        }
        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }
}
