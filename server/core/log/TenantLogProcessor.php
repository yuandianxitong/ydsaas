<?php
declare(strict_types=1);

namespace core\log;

use core\tenant\TenantContext;

class TenantLogProcessor
{
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
        'access_key', 'private_key', 'authorization',
    ];

    public function __invoke(array $record): array
    {
        $snapshot = TenantContext::current();

        $record['context']['tenant_id'] = $snapshot ? $snapshot->id() : 0;
        $record['context']['tenant_code'] = $snapshot ? $snapshot->code() : '';

        if (!empty($record['context'])) {
            $record['context'] = $this->sanitize($record['context']);
        }

        return $record;
    }

    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '******';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }
        return $data;
    }
}
