<?php
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\cache\TagCache;
use core\exception\BusinessException;
use app\repository\saas\PlatformConfigRepository;

class PlatformConfigService extends Service
{
    private const CACHE_TAG = 'platform_config';
    private const CACHE_TTL = 3600;

    protected PlatformConfigRepository $platformConfigRepository;

    public function getConfigGroups(): array
    {
        return [
            'basic'   => '基础设置',
            'storage' => '存储设置',
            'payment' => '支付设置',
            'sms'     => '短信设置',
            'email'   => '邮件设置',
        ];
    }

    public function getConfigsByGroup(string $group = 'basic'): array
    {
        return $this->platformConfigRepository->getByGroup($group);
    }

    public function getConfigById(int $id): ?array
    {
        return $this->platformConfigRepository->findPlatformConfig($id);
    }

    public function updateConfig(int $id, array $data): bool
    {
        $config = $this->getConfigById($id);
        if (!$config) {
            throw new BusinessException('配置项不存在');
        }

        $value = $data['config_value'] ?? '';
        // Type conversion
        $type = $config['config_type'] ?? 'string';
        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'boolean') {
            $value = $value ? '1' : '0';
        }

        $result = $this->platformConfigRepository->updateValue($id, (string) $value);

        $this->clearCache();
        return $result;
    }

    public function batchUpdateConfigs(array $configs): bool
    {
        foreach ($configs as $item) {
            $key = $item['config_key'] ?? '';
            $value = $item['config_value'] ?? '';
            if ($key === '' || !str_starts_with($key, 'platform_')) continue;

            $this->platformConfigRepository->updateByKey(
                $key,
                is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value
            );
        }
        $this->clearCache();
        return true;
    }

    /**
     * Get all active configs as key-value pairs (for frontend init / global endpoint).
     */
    public function getGlobalConfigs(): array
    {
        $cacheKey = 'platform_config_global';
        return TagCache::remember(self::CACHE_TAG, $cacheKey, function () {
            $rows = $this->platformConfigRepository->getAllActive();

            $result = [];
            foreach ($rows as $row) {
                $result[$row['config_key']] = $this->convertValue($row['config_value'], $row['config_type']);
            }
            return $result;
        }, self::CACHE_TTL);
    }

    /**
     * Get a single config value with fallback.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        if (!str_starts_with($key, 'platform_')) {
            return $default;
        }
        $row = $this->platformConfigRepository->findByKey($key);

        if (!$row || $row['config_value'] === '' || $row['config_value'] === null) {
            return $default;
        }

        return $this->convertValue($row['config_value'], $row['config_type']);
    }

    /**
     * 读取平台配置；不存在时用 $factory 生成并持久化（幂等的"按需创建"）。
     * 用于 installation_uuid 这类需要服务端永久稳定的内部值。
     */
    public function getOrCreateValue(string $key, callable $factory): string
    {
        if (!str_starts_with($key, 'platform_')) {
            throw new BusinessException('平台配置 key 必须 platform_ 前缀');
        }

        $row = $this->platformConfigRepository->findByKeyAny($key);
        if ($row && $row['config_value'] !== '' && $row['config_value'] !== null) {
            return (string) $row['config_value'];
        }

        $value = (string) $factory();
        try {
            if ($row) {
                $this->platformConfigRepository->updateValue((int) $row['id'], $value);
            } else {
                $this->platformConfigRepository->createConfig([
                    'config_key'   => $key,
                    'config_value' => $value,
                    'config_group' => 'marketplace',
                    'config_type'  => 'string',
                    'config_name'  => $key,
                    'status'       => 1,
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            // 并发首次创建可能撞 (tenant_id, config_key) 唯一键：重新读取已落库的值
            $fresh = $this->platformConfigRepository->findByKeyAny($key);
            if ($fresh && $fresh['config_value'] !== '' && $fresh['config_value'] !== null) {
                return (string) $fresh['config_value'];
            }
            throw $e;
        }

        $this->clearCache();
        return $value;
    }

    public function clearCache(): void
    {
        TagCache::clear(self::CACHE_TAG);
    }

    private function convertValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) (int) $value,
            'number'  => is_numeric($value) ? (str_contains((string)$value, '.') ? (float)$value : (int)$value) : 0,
            'json'    => is_string($value) ? json_decode($value, true) : $value,
            default   => (string) $value,
        };
    }
}
