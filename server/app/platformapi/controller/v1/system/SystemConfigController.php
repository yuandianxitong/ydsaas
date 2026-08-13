<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\service\saas\PlatformConfigService;
use think\Request;
use think\Response;

class SystemConfigController extends Controller
{
    protected PlatformConfigService $platformConfigService;

    /**
     * Global config for frontend initialization (anonymous access).
     * DB values override hardcoded defaults.
     */
    #[PermissionSkip]
    public function global(): Response
    {
        $dbConfigs = $this->platformConfigService->getGlobalConfigs();

        $defaults = [
            'site_name'        => '平台管理后台',
            'site_logo'        => '',
            'site_description' => 'ydadmin-saas 平台超管',
            'site_favicon'     => '',
            'copyright'        => '© ' . date('Y') . ' ydadmin-saas',
            'version'          => 'v0.2.0',
            'upload'           => [
                'image' => ['max_size' => 5242880, 'ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp']],
            ],
        ];

        foreach ($dbConfigs as $key => $value) {
            // 内部配置（如 marketplace installation_uuid）不下发到匿名前端初始化接口
            if (str_starts_with($key, 'platform_marketplace_')) {
                continue;
            }
            if ($value !== '' && $value !== null && $value !== false) {
                $defaults[$key] = $value;
            }
        }

        return $this->success('', $defaults);
    }

    #[Permission('platform.config.list')]
    public function index(Request $request): Response
    {
        $group = $request->param('group', 'basic');
        return $this->success('', $this->platformConfigService->getConfigsByGroup($group));
    }

    #[Permission('platform.config.list')]
    public function groups(): Response
    {
        return $this->success('', $this->platformConfigService->getConfigGroups());
    }

    #[Permission('platform.config.update')]
    public function update(Request $request, int $id): Response
    {
        $this->platformConfigService->updateConfig($id, $request->param());
        return $this->success();
    }

    #[Permission('platform.config.update')]
    public function batchUpdate(Request $request): Response
    {
        $configs = $request->param('configs', []);
        $this->platformConfigService->batchUpdateConfigs($configs);
        return $this->success();
    }

    #[Permission('platform.config.update')]
    public function clearCache(): Response
    {
        // Clear platform config cache
        $this->platformConfigService->clearCache();

        // Clear menu cache
        $menuRepo = app(\app\repository\saas\PlatformMenuRepository::class);
        $menuRepo->clearCache();

        // Clear permission cache
        $permission = app(\core\auth\Permission::class);
        $permission->clearAllCache();

        // Clear all remaining platform-scoped cache
        // TenantRedisDriver::clear() only clears keys with platform: prefix
        \think\facade\Cache::clear();

        return $this->success(lang('messages.cache_cleared'));
    }
}
