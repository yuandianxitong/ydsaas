<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\tenantapi\controller\v1\diy;

use app\service\diy\SkinPackService;
use app\service\marketplace\MarketplaceThemeInstallService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

/**
 * 整套皮肤包导入导出。
 */
class SkinPackController extends Controller
{
    protected SkinPackService $skinPackService;
    protected MarketplaceThemeInstallService $themeInstallService;

    /** 导出当前租户皮肤包（主题色+TabBar+启动+DIY页）。 */
    #[Permission('diy.home.view')]
    public function export(): Response
    {
        $payload = $this->request->post();
        $result = $this->skinPackService->export([
            'code'           => (string) ($payload['code'] ?? ''),
            'name'           => (string) ($payload['name'] ?? ''),
            'include_custom' => (bool) ($payload['include_custom'] ?? true),
            'page_keys'      => is_array($payload['page_keys'] ?? null) ? $payload['page_keys'] : null,
        ]);

        return download($result['path'], $result['filename'], false);
    }

    /** 上传皮肤包预检（返回 token，不落库）。 */
    #[Permission('diy.home.view')]
    public function import(): Response
    {
        $file = $this->request->file('file');
        if ($file === null) {
            throw new BusinessException('请选择皮肤包 zip 文件', 422);
        }
        $tmp = $file->getPathname();
        if (!is_string($tmp) || $tmp === '' || !is_file($tmp)) {
            throw new BusinessException('上传文件无效', 422);
        }
        $ext = strtolower((string) $file->extension());
        if ($ext !== 'zip') {
            throw new BusinessException('仅支持 zip 皮肤包', 422);
        }

        return $this->success('ok', $this->skinPackService->importPreview($tmp));
    }

    /** 按预检 token 套用皮肤包。 */
    #[Permission('diy.home.save')]
    public function apply(): Response
    {
        $token = (string) ($this->request->post('token') ?? '');
        $data = $this->skinPackService->apply($token, (int) $this->getUserId());

        return $this->success('套用成功', $data);
    }

    /** 官方皮肤包列表（runtime/skin-packages）。 */
    #[Permission('diy.home.view')]
    public function official(): Response
    {
        return $this->success('ok', $this->skinPackService->listOfficial());
    }

    /** 下载官方皮肤包。 */
    #[Permission('diy.home.view')]
    public function officialDownload(): Response
    {
        $code = (string) $this->request->param('code');
        $result = $this->skinPackService->officialZipPath($code);

        return download($result['path'], $result['filename'], false);
    }

    /** 浏览 Site 主题市场（公开列表）。 */
    #[Permission('diy.home.view')]
    public function marketList(): Response
    {
        return $this->success('ok', $this->themeInstallService->listRemote($this->request->get()));
    }

    /**
     * 从官方市场一键安装主题到当前租户。
     * body: { code, version?, auto_apply? }
     */
    #[Permission('diy.home.save')]
    public function marketInstall(): Response
    {
        $code = (string) ($this->request->post('code') ?? '');
        $version = $this->request->post('version');
        $autoApply = (bool) ($this->request->post('auto_apply') ?? true);
        $data = $this->themeInstallService->install(
            $code,
            is_string($version) && $version !== '' ? $version : null,
            $autoApply,
            (int) $this->getUserId()
        );

        return $this->success(($data['applied'] ?? null) ? '安装并套用成功' : '已下载预检', $data);
    }
}
