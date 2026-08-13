<?php
declare(strict_types=1);

namespace core\tenant;

use core\exception\BusinessException;

class TenantNotFoundException extends BusinessException
{
    public function __construct(string $tenantCode = '')
    {
        $msg = $tenantCode === ''
            ? lang('messages.tenant_not_found')
            : lang('messages.tenant_not_found_code', ['code' => $tenantCode]);
        parent::__construct($msg, 404);
    }
}
