<?php
declare(strict_types=1);

namespace core\tenant;

final class TenantContextSnapshot
{
    public function __construct(private readonly array $data) {}

    public function id(): int
    {
        return (int) $this->data['id'];
    }

    public function code(): string
    {
        return (string) $this->data['code'];
    }

    public function isPlatform(): bool
    {
        return (bool) $this->data['is_platform'];
    }

    public function raw(): array
    {
        return $this->data['raw'] ?? [];
    }

    /**
     * 获取 tenant 表原始行数据（解包 raw() 的嵌套结构）。
     *
     * TenantContextMiddleware.set() 传入 {id, code, is_platform, raw: $tenantRow}，
     * TenantContext::set() 再把整个入参存为 raw，形成双重嵌套。
     * 此方法统一解包，避免各调用方重复 unwrap。
     */
    public function tenantData(): array
    {
        $wrapper = $this->data['raw'] ?? [];
        return is_array($wrapper['raw'] ?? null) ? $wrapper['raw'] : $wrapper;
    }
}
