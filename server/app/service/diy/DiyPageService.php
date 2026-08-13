<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyPageRepository;
use core\base\Service;
use core\diy\DiyWidgetRegistry;
use core\exception\BusinessException;

class DiyPageService extends Service
{
    protected DiyPageRepository $repo;
    protected \core\diy\DiyWidgetCatalog $widgetCatalog;

    // 首尾必须是字母/数字，中间可含连字符；总长 2-64（禁首尾连字符）
    private const SLUG_RE = '/^[a-z0-9][a-z0-9-]{0,62}[a-z0-9]$/';

    /** 系统保留装修页：key => 建行属性。这些 key 不可被自定义页占用，走 pages/:key 通道编辑。 */
    private const SYSTEM_PAGES = [
        'home' => ['page_type' => 'home', 'title' => '首页'],
        'member' => ['page_type' => 'member', 'title' => '个人中心'],
    ];

    // ---------- 按 key 通用 ----------

    /** 取某页草稿；不存在返回空结构。 */
    public function getDraft(string $key): array
    {
        $row = $this->repo->findByKey($key);
        $fallbackTitle = self::SYSTEM_PAGES[$key]['title'] ?? $key;
        if (!is_array($row)) {
            return [
                'title'         => $fallbackTitle,
                'components'    => [],
                'page_settings' => [],
            ];
        }

        return [
            'title'         => (string) (($row['title'] ?? '') !== '' ? $row['title'] : $fallbackTitle),
            'components'    => $row['components_draft'] ?? [],
            'page_settings' => $row['page_settings'] ?? [],
        ];
    }

    /**
     * 保存某页草稿（校验组件树）。home 行不存在时自动建（兼容首次进入首页装修）。
     * @param string|null $title 非 null 时同步更新 diy_pages.title
     */
    public function saveDraft(string $key, array $components, array $pageSettings, ?string $title = null): void
    {
        $tid   = \core\tenant\TenantContext::current()?->id() ?? 0;
        $clean = (new DiyWidgetRegistry())->validate($components, $this->widgetCatalog->typesForTenant($tid));
        $now   = date('Y-m-d H:i:s');
        $row   = $this->repo->findByKey($key);
        $title = $title !== null ? mb_substr(trim($title), 0, 64) : null;

        if ($row === null) {
            if (!isset(self::SYSTEM_PAGES[$key])) {
                throw new BusinessException('页面不存在', 404);
            }
            // 系统页首存：自动建行（不传 tenant_id，Repository 从 TenantContext 注入）
            $this->repo->create([
                'page_type'        => self::SYSTEM_PAGES[$key]['page_type'],
                'page_key'         => $key,
                'platform'         => 'uniapp',
                'title'            => ($title !== null && $title !== '') ? $title : self::SYSTEM_PAGES[$key]['title'],
                'components_draft' => $clean,
                'page_settings'    => $pageSettings,
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            return;
        }

        // update() 走原生 Query，不经 Model cast，必须手动 json_encode
        $patch = [
            'components_draft' => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'page_settings'    => json_encode($pageSettings, JSON_UNESCAPED_UNICODE),
            'updated_at'       => $now,
        ];
        if ($title !== null && $title !== '') {
            $patch['title'] = $title;
        }
        $this->repo->update((int) $row['id'], $patch);
    }

    /** 发布某页：原子拷贝草稿 -> 已发布，并写版本快照（同一事务，避免发布成功但快照丢失）。 */
    public function publish(string $key, int $createdBy = 0, string $note = ''): void
    {
        $note = trim($note);
        if ($note === '') {
            throw new BusinessException('请填写发版名称', 400);
        }
        $this->runInTransaction(function () use ($key, $createdBy, $note): void {
            if ($this->repo->publishByKey($key) === 0) {
                throw new BusinessException('请先保存草稿再发布', 422);
            }
            // 事务内重读，拿到刚发布的内容写快照
            $row = $this->repo->findByKey($key);
            if ($row === null) {
                throw new BusinessException('发布失败：页面不存在', 404);
            }
            $this->repo->insertVersion(
                (int) $row['id'],
                $row['components_published'] ?? [],
                $row['page_settings'] ?? [],
                $createdBy,
                $note
            );
        });
    }

    /** 某页版本列表（最新在前）。 */
    public function listPageVersions(string $key): array
    {
        $row = $this->repo->findByKey($key);
        return $row === null ? [] : $this->repo->listVersions((int) $row['id']);
    }

    /** 回滚某版本到该页草稿（校验 version 属于该页，防跨页越权）。 */
    public function restorePageVersion(string $key, int $versionId): void
    {
        $page = $this->repo->findByKey($key);
        if ($page === null) {
            throw new BusinessException('页面不存在', 404);
        }
        $ver = $this->repo->findVersion($versionId);
        if ($ver === null || (int) $ver['page_id'] !== (int) $page['id']) {
            throw new BusinessException('版本不存在', 404);
        }
        $this->repo->restoreDraft((int) $page['id'], $ver['components'] ?? [], $ver['page_settings'] ?? []);
    }

    /** 供 c-api：按显式 tenant 取某页已发布树，未发布/禁用/不存在返回 null。 */
    public function getPublishedForTenant(int $tenantId, string $key, string $platform = 'uniapp'): ?array
    {
        $row = $this->repo->findByKeyForTenant($tenantId, $key, $platform);
        if ($row === null || (int) ($row['status'] ?? 0) !== 1 || empty($row['components_published'])) {
            return null;
        }

        return [
            'title'         => (string) ($row['title'] ?? (self::SYSTEM_PAGES[$key]['title'] ?? $key)),
            'components'    => $this->hydrateComponents((array) $row['components_published'], $tenantId),
            'page_settings' => $row['page_settings'] ?? [],
        ];
    }

    /**
     * 编辑器画布预览注水：单组件跑 hydrator 返回真实数据 props（与 C 端下发共享同一份注水逻辑）。
     * 与 hydrateComponents 的 fail-safe 批处理语义不同：未授权/未知类型显式报错（编辑器需要明确反馈），
     * hydrator 异常向上透传（由全局异常处理转为接口错误，编辑器侧回退骨架占位）。
     * @param array<string,mixed> $props
     * @return array<string,mixed>
     */
    public function hydratePreviewWidget(string $type, array $props, int $tenantId): array
    {
        if (!in_array($type, $this->widgetCatalog->typesForTenant($tenantId), true)) {
            throw new BusinessException('组件类型不存在或未授权', 404);
        }
        $hydrator = $this->widgetCatalog->hydratorFor($type, $tenantId);
        if ($hydrator === null) {
            return $props; // 内置组件 / 装饰型插件 widget：无 hydrator，原样回显
        }

        return \core\diy\NormalizedWidget::normalize(
            $hydrator->hydrate($props, $tenantId),
            $this->widgetCatalog->widgetMetaForNormalize($type, $tenantId)
        );
    }

    /**
     * 注水插件 widget + 丢弃未知/未授权组件（fail-safe）。
     * 内置类型原样；授权插件数据 widget 调 hydrator（异常→丢弃+log）；
     * 其它（未知/未授权/已禁用插件）→ 丢弃。
     * @param array<int,mixed> $components
     * @return array<int,array<string,mixed>>
     */
    private function hydrateComponents(array $components, int $tenantId): array
    {
        $allowed = $this->widgetCatalog->typesForTenant($tenantId);
        $out = [];
        foreach ($components as $c) {
            if (!is_array($c)) {
                continue;
            }
            $type = (string) ($c['type'] ?? '');
            if (!in_array($type, $allowed, true)) {
                continue; // 未知 / 未授权 / 插件已禁用 → 丢弃
            }
            $hydrator = $this->widgetCatalog->hydratorFor($type, $tenantId);
            if ($hydrator !== null) {
                try {
                    $c['props'] = \core\diy\NormalizedWidget::normalize(
                        $hydrator->hydrate((array) ($c['props'] ?? []), $tenantId),
                        $this->widgetCatalog->widgetMetaForNormalize($type, $tenantId)
                    );
                } catch (\Throwable $e) {
                    error_log(sprintf('[diy] hydrate failed type=%s id=%s tenant=%d: %s', $type, (string) ($c['id'] ?? '?'), $tenantId, $e->getMessage()));
                    continue; // 丢弃该组件
                }
            }
            $out[] = $c;
        }
        return $out;
    }

    // ---------- 页面 CRUD ----------

    /**
     * 自定义页分页列表（不含 home）。
     * @param bool|null $published null=全部；true=已发布；false=草稿
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function listPages(int $page = 1, int $limit = 10, string $keyword = '', ?bool $published = null): array
    {
        return $this->repo->listPages(max(1, $page), max(1, min(100, $limit)), trim($keyword), $published);
    }

    /**
     * 复制自定义页：草稿 = 源页草稿（源无草稿时用已发布内容），发布态不复制（副本恒为未发布草稿）；
     * 新标识自动生成 <源标识>-copy[N]（去重、保证 ≤64 位 slug 合法）。返回新页 id。
     */
    public function copyPage(int $id): int
    {
        $src = $this->guardCustom($id);

        $draft = $src['components_draft'] ?? [];
        if (!is_array($draft) || $draft === []) {
            $draft = is_array($src['components_published'] ?? null) ? $src['components_published'] : [];
        }

        $now = date('Y-m-d H:i:s');
        $row = $this->repo->create([
            'page_type'        => 'custom',
            'page_key'         => $this->nextCopyKey((string) $src['page_key']),
            'platform'         => 'uniapp',
            'title'            => mb_substr((string) $src['title'], 0, 90) . '-副本',
            'components_draft' => $draft,
            'page_settings'    => $src['page_settings'] ?? [],
            'status'           => (int) ($src['status'] ?? 1),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) ($row['id'] ?? 0);
    }

    /** 生成不冲突的副本标识：<key>-copy、<key>-copy2…；截断源 key 保证总长 ≤64。 */
    private function nextCopyKey(string $sourceKey): string
    {
        // 预留 "-copyNN" 7 位后缀空间；截断后去掉尾部连字符保证 slug 合法
        $base = rtrim(mb_substr($sourceKey, 0, 64 - 7), '-');
        for ($i = 1; $i <= 99; $i++) {
            $key = $base . '-copy' . ($i === 1 ? '' : $i);
            if (!$this->repo->existsKey($key)) {
                return $key;
            }
        }
        throw new BusinessException('副本过多，请先清理', 422);
    }

    /** 新建自定义页（校验 slug）。返回新 id。 */
    public function createPage(string $title, string $key): int
    {
        $this->assertSlug($key);
        if ($this->repo->existsKey($key)) {
            throw new BusinessException('页面标识已存在', 409);
        }
        $now = date('Y-m-d H:i:s');

        $row = $this->repo->create([
            'page_type'        => 'custom',
            'page_key'         => $key,
            'platform'         => 'uniapp',
            'title'            => $title !== '' ? $title : $key,
            'components_draft' => [],
            'status'           => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) ($row['id'] ?? 0);
    }

    /** 改标题。 */
    public function renamePage(int $id, string $title): void
    {
        $this->guardCustom($id);
        $this->repo->renamePage($id, $title);
    }

    /** 改 slug（校验 + 唯一性，禁止与现有冲突）。 */
    public function updateSlug(int $id, string $key): void
    {
        $row = $this->guardCustom($id);
        $this->assertSlug($key);
        if ($key !== $row['page_key'] && $this->repo->existsKey($key)) {
            throw new BusinessException('页面标识已存在', 409);
        }
        $this->repo->updateSlug($id, $key);
    }

    /** 启停。 */
    public function setStatus(int $id, int $status): void
    {
        $this->guardCustom($id);
        $this->repo->setStatus($id, $status === 1 ? 1 : 0);
    }

    /** 软删（仅自定义页）。 */
    public function deletePage(int $id): void
    {
        $this->guardCustom($id);
        $this->repo->softDelete($id);
    }

    // ---------- home 薄包装（兼容既有调用/路由） ----------

    public function getHomeDraft(): array
    {
        return $this->getDraft('home');
    }

    public function saveHomeDraft(array $components, array $pageSettings): void
    {
        $this->saveDraft('home', $components, $pageSettings);
    }

    public function publishHome(int $createdBy = 0, string $note = ''): void
    {
        $this->publish('home', $createdBy, $note);
    }

    public function listHomeVersions(): array
    {
        return $this->listPageVersions('home');
    }

    public function restoreHomeVersion(int $versionId): void
    {
        $this->restorePageVersion('home', $versionId);
    }

    public function getPublishedHome(): ?array
    {
        $tid = \core\tenant\TenantContext::current()?->id();
        // 无租户上下文时返回 null，而非回落到模板租户(0) 的首页
        return $tid === null ? null : $this->getPublishedForTenant($tid, 'home');
    }

    public function getPublishedHomeForTenant(int $tenantId): ?array
    {
        return $this->getPublishedForTenant($tenantId, 'home');
    }

    /** 页面装修列表卡片用的 home 状态摘要（薄包装，端点兼容保留）。 */
    public function getHomeSummary(): array
    {
        return $this->getPageSummary('home');
    }

    /** 系统页状态摘要（装修列表卡片；key 限系统保留页）。 */
    public function getPageSummary(string $key): array
    {
        if (!isset(self::SYSTEM_PAGES[$key])) {
            throw new BusinessException('仅支持系统页面摘要', 422);
        }
        $row = $this->repo->getPageSummary($key);

        return [
            'title' => $row['title'] ?? self::SYSTEM_PAGES[$key]['title'],
            // 先转 int 再转 bool：部分 PDO 驱动把布尔表达式返回为字符串 '0'，(bool)'0' 在 PHP 为 true
            'published' => (bool) (int) ($row['published'] ?? 0),
            'component_count' => (int) ($row['component_count'] ?? 0),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    // ---------- 私有 ----------

    /** slug 规则校验：小写字母数字与连字符，2-64 位，首字符非连字符；系统保留 key 拒绝。 */
    private function assertSlug(string $key): void
    {
        if (isset(self::SYSTEM_PAGES[$key]) || !preg_match(self::SLUG_RE, $key)) {
            throw new BusinessException('页面标识不合法（小写字母/数字/连字符，2-64位，不可为系统保留标识）', 422);
        }
    }

    /** 确认目标 id 是当前租户的自定义页（非系统保留页、存在）。返回行。 */
    private function guardCustom(int $id): array
    {
        $row = $this->repo->find($id);
        if ($row === null || ($row['page_type'] ?? '') !== 'custom') {
            throw new BusinessException('页面不存在', 404);
        }
        return $row;
    }
}
