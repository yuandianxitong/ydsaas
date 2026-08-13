<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use app\repository\plugin\PluginRepository;
use app\service\plugin\PluginPackageService;
use app\service\plugin\PluginService;
use app\validate\plugin\PluginValidate;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

class PluginController extends Controller
{
    protected PluginPackageService $packageService;
    protected PluginService $pluginService;
    protected PluginRepository $pluginRepo;

    #[Permission('platform.plugin.list')]
    public function index(): Response
    {
        $page  = (int) $this->request->param('page', 1);
        $limit = (int) $this->request->param('limit', 20);
        $kind  = $this->request->param('kind');
        if ($kind !== null && $kind !== '' && !in_array($kind, ['app', 'plugin'], true)) {
            throw new BusinessException('kind 参数无效', 422);
        }
        $result = $this->pluginRepo->listWithKind($kind ?: null, $page, $limit);
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('platform.plugin.detail')]
    public function show($id): Response
    {
        $row = $this->pluginRepo->find((int) $id);
        if (!$row) {
            throw new BusinessException('插件不存在', 404);
        }
        // v2.7.4：show 是 API 边界 → 把 icon 字段（manifest 原值）转为浏览器可拉的 URL
        $row['icon'] = \core\plugin\PluginIconResolver::iconUrl(
            (string) $row['code'],
            (string) ($row['icon'] ?? '')
        );
        return $this->success(lang('messages.get_success'), $row);
    }

    #[Permission('platform.plugin.upload')]
    public function upload(): Response
    {
        $this->validate($this->request->post() + $this->request->file(), PluginValidate::class, [], 'upload');

        $file = $this->request->file('plugin');
        if (!$file) {
            throw new BusinessException('未收到 zip', 422);
        }
        $result = $this->packageService->upload($file->getRealPath());
        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('platform.plugin.install')]
    public function install($id): Response
    {
        return $this->success(lang('messages.update_success'), $this->pluginService->install((int) $id));
    }

    /**
     * 直接从磁盘登记并安装一个本地（git 一起带的）插件。
     * body: { code: string }
     *
     * 用途：用户在管理端"应用管理"列表看到磁盘扫到的虚拟行（id=0），
     * 点击安装时走这里——不需要 zip 上传流程。
     */
    #[Permission('platform.plugin.install')]
    public function installFromDisk(): Response
    {
        $code = trim((string) $this->request->param('code', ''));
        if ($code === '') {
            throw new BusinessException('code 不能为空', 422);
        }
        $registered = $this->packageService->registerFromDisk($code);
        $installed  = $this->pluginService->install((int) $registered['plugin_id']);
        return $this->success(lang('messages.update_success'), $installed);
    }

    #[Permission('platform.plugin.uninstall')]
    public function uninstall($id): Response
    {
        return $this->success(lang('messages.delete_success'), $this->pluginService->uninstall((int) $id));
    }

    #[Permission('platform.plugin.status')]
    public function disable($id): Response
    {
        return $this->success(lang('messages.update_success'), $this->pluginService->disable((int) $id));
    }

    #[Permission('platform.plugin.status')]
    public function enable($id): Response
    {
        return $this->success(lang('messages.update_success'), $this->pluginService->enable((int) $id));
    }

    #[Permission('platform.plugin.list')]
    public function options(): Response
    {
        return $this->success(lang('messages.get_success'), $this->pluginRepo->listAvailableForGrant());
    }

    #[Permission('platform.plugin.upgrade')]
    public function upgrade($id): Response
    {
        $file = $this->request->file('plugin');
        if (!$file) {
            throw new BusinessException('未收到 zip', 422);
        }
        return $this->success(
            lang('messages.update_success'),
            app(\app\service\plugin\PluginUpgradeService::class)->upgrade((int) $id, $file->getRealPath())
        );
    }

    #[Permission('platform.plugin.purge')]
    public function purgeData($id): Response
    {
        $force = (bool) $this->request->param('force', false);
        return $this->success(
            lang('messages.delete_success'),
            app(\app\service\plugin\PluginPurgeService::class)->purge((int) $id, $force)
        );
    }
}
