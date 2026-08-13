<?php
declare(strict_types=1);

namespace core\tenant;

use core\exception\BusinessException;

class TenantInactiveException extends BusinessException
{
    public function __construct(string $reason = '租户已过期或已冻结')
    {
        parent::__construct($reason, 402);
    }
}
