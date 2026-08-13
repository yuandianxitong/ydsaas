<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\tenantapi\controller\v1\diy;

use app\service\diy\DiyPageService;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

/**
 * 租户后台：首页装修（DIY）控制器。
 *
 * GET  /tenantapi/diy/home         → 读取草稿
 * PUT  /tenantapi/diy/home         → 保存草稿
 * POST /tenantapi/diy/home/publish → 发布
 */
class DiyPageController extends Controller
{
    protected DiyPageService $diyPageService;
    protected \core\diy\DiyWidgetCatalog $widgetCatalog;
    protected \app\service\diy\LinkCatalogService $linkCatalogService;
    protected \core\member\MemberStatCatalog $memberStatCatalog;

    #[Permission('diy.home.view')]
    public function getHome(): Response
    {
        return $this->success('ok', $this->diyPageService->getHomeDraft());
    }

    #[Permission('diy.home.save')]
    public function saveHome(): Response
    {
        $payload      = $this->request->put();
        $components   = (array) ($payload['components'] ?? []);
        $pageSettings = (array) ($payload['page_settings'] ?? []);
        $this->diyPageService->saveHomeDraft($components, $pageSettings);

        return $this->success('保存成功');
    }

    #[Permission('diy.home.publish')]
    public function publishHome(): Response
    {
        $payload = $this->request->post();
        $note = trim((string) ($payload['note'] ?? $payload['name'] ?? ''));
        $this->diyPageService->publishHome((int) $this->getUserId(), $note);

        return $this->success('发布成功');
    }

    #[Permission('diy.home.version.view')]
    public function versions(): Response
    {
        return $this->success('ok', $this->diyPageService->listHomeVersions());
    }

    /** 页面装修列表：home 状态摘要。 */
    #[Permission('diy.home.view')]
    public function homeSummary(): Response
    {
        return $this->success('ok', $this->diyPageService->getHomeSummary());
    }

    /** 页面装修列表：系统页状态摘要（home/member）。 */
    #[Permission('diy.home.view')]
    public function pageSummary(): Response
    {
        return $this->success('ok', $this->diyPageService->getPageSummary((string) $this->request->param('key')));
    }

    #[Permission('diy.home.version.restore')]
    public function restoreVersion(): Response
    {
        $id = (int) $this->request->param('id');
        $this->diyPageService->restoreHomeVersion($id);

        return $this->success('已回滚到草稿');
    }

    // ---------- 自定义页面管理 ----------

    #[Permission('diy.page.view')]
    public function listPages(): Response
    {
        $published = $this->request->param('published', '');
        return $this->success('ok', $this->diyPageService->listPages(
            (int) $this->request->param('page', 1),
            (int) $this->request->param('limit', 10),
            (string) $this->request->param('keyword', ''),
            $published === '' ? null : (bool) (int) $published,
        ));
    }

    /** 复制自定义页（副本恒为未发布草稿，标识自动生成 <源>-copyN）。 */
    #[Permission('diy.page.create')]
    public function copyPage(): Response
    {
        $id = $this->diyPageService->copyPage((int) $this->request->param('id'));
        return $this->success('复制成功', ['id' => $id]);
    }

    #[Permission('diy.page.create')]
    public function createPage(): Response
    {
        $p = $this->request->post();
        $id = $this->diyPageService->createPage((string) ($p['title'] ?? ''), (string) ($p['page_key'] ?? ''));
        return $this->success('创建成功', ['id' => $id]);
    }

    #[Permission('diy.page.update')]
    public function updatePage(): Response
    {
        $id = (int) $this->request->param('id');
        $p  = $this->request->put();
        if (isset($p['title'])) {
            $this->diyPageService->renamePage($id, (string) $p['title']);
        }
        if (isset($p['page_key'])) {
            $this->diyPageService->updateSlug($id, (string) $p['page_key']);
        }
        if (isset($p['status'])) {
            $this->diyPageService->setStatus($id, (int) $p['status']);
        }
        return $this->success('保存成功');
    }

    #[Permission('diy.page.delete')]
    public function deletePage(): Response
    {
        $this->diyPageService->deletePage((int) $this->request->param('id'));
        return $this->success('已删除');
    }

    // ---------- 按 key 的草稿/发布/版本（自定义页） ----------

    #[Permission('diy.page.view')]
    public function getDraftByKey(): Response
    {
        return $this->success('ok', $this->diyPageService->getDraft((string) $this->request->param('key')));
    }

    #[Permission('diy.page.save')]
    public function saveDraftByKey(): Response
    {
        $key     = (string) $this->request->param('key');
        $payload = $this->request->put();
        $title   = array_key_exists('title', $payload) ? (string) $payload['title'] : null;
        $this->diyPageService->saveDraft(
            $key,
            (array) ($payload['components'] ?? []),
            (array) ($payload['page_settings'] ?? []),
            $title
        );
        return $this->success('保存成功');
    }

    #[Permission('diy.page.publish')]
    public function publishByKey(): Response
    {
        $payload = $this->request->post();
        $note = trim((string) ($payload['note'] ?? $payload['name'] ?? ''));
        $this->diyPageService->publish(
            (string) $this->request->param('key'),
            (int) $this->getUserId(),
            $note
        );
        return $this->success('发布成功');
    }

    #[Permission('diy.page.view')]
    public function versionsByKey(): Response
    {
        return $this->success('ok', $this->diyPageService->listPageVersions((string) $this->request->param('key')));
    }

    #[Permission('diy.page.save')]
    public function restoreVersionByKey(): Response
    {
        $this->diyPageService->restorePageVersion((string) $this->request->param('key'), (int) $this->request->param('id'));
        return $this->success('已回滚到草稿');
    }

    /** 装修链接目录：内置页 + 自建页 + 授权插件链接（+ 链接库）。 */
    #[Permission('diy.home.view')]
    public function linkCatalog(): Response
    {
        $tid = \core\tenant\TenantContext::current()?->id() ?? 0;
        return $this->success('ok', ['links' => $this->linkCatalogService->catalog($tid)]);
    }

    /** 编辑器组件目录：内置 type 列表 + 该租户授权的插件 widget 元数据。 */
    #[Permission('diy.home.view')]
    public function widgets(): Response
    {
        $tid = \core\tenant\TenantContext::current()?->id() ?? 0;
        return $this->success('ok', [
            'builtins'     => \core\diy\DiyWidgetRegistry::TYPES,
            'plugins'      => $this->widgetCatalog->pluginWidgetMetaForTenant($tid),
            'member_stats' => $this->memberStatCatalog->forTenant($tid),
        ]);
    }

    /** 编辑器画布预览注水：单组件跑 hydrator 返回真实数据 props（与 C 端下发共享同一份注水逻辑）。 */
    #[Permission('diy.home.view')]
    public function widgetPreview(): Response
    {
        $tid   = \core\tenant\TenantContext::current()?->id() ?? 0;
        $type  = (string) $this->request->post('type', '');
        $props = (array) $this->request->post('props', []);

        return $this->success('ok', ['props' => $this->diyPageService->hydratePreviewWidget($type, $props, $tid)]);
    }
}
