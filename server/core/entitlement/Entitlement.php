<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\entitlement;

/**
 * 租户权益 value object。表示"租户当前可使用某个 app/plugin 的能力"。
 * 不可变；构造后字段只读。
 */
final class Entitlement
{
    public function __construct(
        public readonly string $code,
        public readonly string $kind,        // 'app' | 'plugin'
        public readonly string $source,      // 'plan' | 'purchase'
        public readonly ?string $expiresAt,
    ) {}

    /** @return array{code:string,kind:string,source:string,expires_at:?string} */
    public function toArray(): array
    {
        return [
            'code'       => $this->code,
            'kind'       => $this->kind,
            'source'     => $this->source,
            'expires_at' => $this->expiresAt,
        ];
    }
}
