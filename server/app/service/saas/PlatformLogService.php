<?php
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use think\facade\Db;

class PlatformLogService extends Service
{
    public function getLoginLogs(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 15);

        // 表字段与前端 prop 不一致：status/message/created_at/user_agent
        // → login_result/login_message/login_time/browser+os；username 需 join。
        $query = Db::table('platform_login_logs')
            ->alias('l')
            ->leftJoin('platform_admins a', 'l.platform_admin_id = a.id')
            ->field([
                'l.id',
                'l.platform_admin_id',
                'a.username',
                'l.ip',
                'l.user_agent',
                'l.status as login_result',
                'l.message as login_message',
                'l.created_at as login_time',
            ])
            ->order('l.id', 'desc');

        if (!empty($params['keyword'])) {
            $query->whereLike('a.username', "%{$params['keyword']}%");
        }
        if (!empty($params['ip'])) {
            $query->whereLike('l.ip', "%{$params['ip']}%");
        }
        if (isset($params['login_result']) && $params['login_result'] !== '') {
            $query->where('l.status', (int) $params['login_result']);
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        foreach ($list as &$row) {
            $ua = (string) ($row['user_agent'] ?? '');
            $row['browser'] = $this->parseBrowser($ua);
            $row['os'] = $this->parseOs($ua);
            unset($row['user_agent']);
        }
        unset($row);

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / max($limit, 1)),
            ],
        ];
    }

    public function getOperationLogs(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 15);

        // 表字段与前端 prop 不一致：duration_ms/created_at → execution_time(秒)/operation_time；username 需 join。
        $query = Db::table('platform_operation_logs')
            ->alias('l')
            ->leftJoin('platform_admins a', 'l.platform_admin_id = a.id')
            ->field([
                'l.id',
                'l.platform_admin_id',
                'a.username',
                'l.method',
                'l.path',
                'l.ip',
                'l.response_code',
                'l.duration_ms',
                'l.created_at as operation_time',
            ])
            ->order('l.id', 'desc');

        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->whereLike('a.username', "%{$params['keyword']}%")
                  ->whereOr('l.path', 'like', "%{$params['keyword']}%");
            });
        }
        if (!empty($params['method'])) {
            $query->where('l.method', strtoupper($params['method']));
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        foreach ($list as &$row) {
            // 前端按「秒 × 1000」渲染毫秒
            $row['execution_time'] = ((int) ($row['duration_ms'] ?? 0)) / 1000;
            unset($row['duration_ms']);
        }
        unset($row);

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / max($limit, 1)),
            ],
        ];
    }

    public function getAuditLogs(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 15);

        // 表无 result_code：结果在 result JSON；管理员名优先 admins.nickname/username，回退日志快照
        $query = Db::table('admin_operation_logs')
            ->alias('l')
            ->leftJoin('tenants t', 'l.tenant_id = t.id')
            ->leftJoin('admins a', 'l.admin_id = a.id AND l.tenant_id = a.tenant_id')
            ->order('l.id', 'desc')
            ->field([
                'l.id',
                'l.tenant_id',
                'l.admin_id',
                'l.username',
                'l.method',
                'l.path',
                'l.ip',
                'l.action',
                'l.description',
                'l.result',
                'l.execution_time',
                'l.operation_time',
                'l.created_at',
                't.name as tenant_name',
                'a.nickname as admin_nickname',
                'a.username as admin_username',
            ]);

        if (!empty($params['tenant_id'])) {
            $query->where('l.tenant_id', (int) $params['tenant_id']);
        }
        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $kw = "%{$params['keyword']}%";
                $q->whereLike('l.username', $kw)
                    ->whereOr('a.username', 'like', $kw)
                    ->whereOr('a.nickname', 'like', $kw)
                    ->whereOr('l.path', 'like', $kw);
            });
        }
        if (!empty($params['method'])) {
            $query->where('l.method', strtoupper($params['method']));
        }
        // 业务时间在 operation_time；created_at 仅为入库时间
        if (!empty($params['date_from'])) {
            $query->where('l.operation_time', '>=', $params['date_from'] . ' 00:00:00');
        }
        if (!empty($params['date_to'])) {
            $query->where('l.operation_time', '<=', $params['date_to'] . ' 23:59:59');
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        foreach ($list as &$row) {
            $nickname = trim((string) ($row['admin_nickname'] ?? ''));
            $adminUser = trim((string) ($row['admin_username'] ?? ''));
            $snapUser = trim((string) ($row['username'] ?? ''));
            $row['admin_name'] = $nickname !== '' ? $nickname : ($adminUser !== '' ? $adminUser : $snapUser);

            $result = $row['result'] ?? null;
            if (is_string($result)) {
                $decoded = json_decode($result, true);
                $result = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($result)) {
                $result = [];
            }
            $code = $result['code'] ?? null;
            $row['result_code'] = $code === null || $code === '' ? null : (int) $code;

            // 前端 prop 用 created_at；统一落到 operation_time
            $opTime = $row['operation_time'] ?? null;
            if (!empty($opTime)) {
                $row['created_at'] = $opTime;
            }

            unset($row['admin_nickname'], $row['admin_username'], $row['result']);
        }
        unset($row);

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / max($limit, 1)),
            ],
        ];
    }

    protected function parseBrowser(string $userAgent): string
    {
        $browsers = [
            'Edge'    => '/Edg(?:e)?\/([0-9.]+)/',
            'Chrome'  => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari'  => '/Version\/([0-9.]+).*Safari/',
            'Opera'   => '/(?:Opera|OPR)\/([0-9.]+)/',
            'IE'      => '/MSIE ([0-9.]+)/',
        ];

        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return $browser . ' ' . $matches[1];
            }
        }

        return 'Unknown';
    }

    protected function parseOs(string $userAgent): string
    {
        $systems = [
            'Windows 10'  => '/Windows NT 10.0/',
            'Windows 8.1' => '/Windows NT 6.3/',
            'Windows 8'   => '/Windows NT 6.2/',
            'Windows 7'   => '/Windows NT 6.1/',
            'Mac OS X'    => '/Mac OS X ([0-9_]+)/',
            'Linux'       => '/Linux/',
            'Android'     => '/Android ([0-9.]+)/',
            'iOS'         => '/iPhone OS ([0-9_]+)/',
        ];

        foreach ($systems as $system => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $system;
            }
        }

        return 'Unknown';
    }
}
