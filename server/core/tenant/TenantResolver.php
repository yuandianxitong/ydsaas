<?php
declare(strict_types=1);

namespace core\tenant;

class TenantResolver
{
    /** @var string[]  保留子域名 */
    public const RESERVED_SUBDOMAINS = [
        'admin', 'www', 'api', 'docs', 'mail', 'ftp', 'webmail', 'cpanel',
        'm', 'mobile', 'app', 'status', 'blog', 'help', 'support',
    ];

    /**
     * @param string[] $rootDomains  支持的根域名清单（按长度从长到短匹配）
     */
    public function __construct(private array $rootDomains)
    {
        // 长度从长到短排序，确保 local.app.com 优先于 app.com 匹配
        usort($this->rootDomains, fn($a, $b) => strlen($b) <=> strlen($a));
    }

    /**
     * 从 Host 解析 tenant_code（子域名部分）。
     * 返回 null 表示未识别（根域名 / 保留子域名 / 未知域名）。
     */
    public function parseSubdomain(string $host): ?string
    {
        // 去掉端口
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }
        $host = strtolower($host);

        foreach ($this->rootDomains as $root) {
            $rootLower = strtolower($root);
            if ($host === $rootLower) {
                return null; // 根域名本身
            }
            $suffix = '.' . $rootLower;
            if (str_ends_with($host, $suffix)) {
                $sub = substr($host, 0, -strlen($suffix));
                // 子域名包含点说明是二级以下子域，不当作 tenant_code
                if (str_contains($sub, '.')) {
                    return null;
                }
                if (in_array($sub, self::RESERVED_SUBDOMAINS, true)) {
                    return null;
                }
                return $sub === '' ? null : $sub;
            }
        }
        return null;
    }
}
