<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyLinkRepository;
use app\repository\diy\DiyPageRepository;
use core\base\Service;
use core\diy\DiyLinkCatalog;

/**
 * 装修链接目录：合并 内置基础页 + 租户自建装修页 + 授权插件链接 + 租户链接库。
 * 返回扁平链接数组，前端按 category 分组。
 */
class LinkCatalogService extends Service
{
    protected DiyPageRepository $pageRepository;
    protected DiyLinkCatalog    $linkCatalog;
    protected DiyLinkRepository $linkRepository;

    /** 内置基础页（单一来源，替代旧前端 linkConfig.ts）。 */
    public const BASE_LINKS = [
        ['label' => '首页',     'path' => '/pages/index/index',    'category' => '基础页面'],
        ['label' => '发现',     'path' => '/pages/discover/index', 'category' => '基础页面'],
        ['label' => '消息',     'path' => '/pages/message/index',  'category' => '基础页面'],
        ['label' => '我的',     'path' => '/pages/my/index',       'category' => '基础页面'],
        ['label' => '余额',     'path' => '/pages/balance',        'category' => '用户中心'],
        ['label' => '积分',     'path' => '/pages/points',         'category' => '用户中心'],
        ['label' => '设置',     'path' => '/pages/settings',       'category' => '用户中心'],
        ['label' => '意见反馈', 'path' => '/pages/feedback',       'category' => '用户中心'],
        ['label' => '关于我们', 'path' => '/pages/about',          'category' => '用户中心'],
    ];

    /** @return array<int,array{label:string,path:string,category:string,source:string,params_schema:array,external:bool}> */
    public function catalog(int $tenantId): array
    {
        $out = [];
        foreach (self::BASE_LINKS as $b) {
            $out[] = $this->item($b['label'], $b['path'], $b['category'], 'builtin');
        }
        foreach ($this->pageRepository->listCustomLinkPages() as $p) {
            $out[] = $this->item($p['label'], $p['path'], '自定义页面', 'custom-page');
        }
        foreach ($this->linkCatalog->pluginLinksForTenant($tenantId) as $l) {
            $out[] = $l; // 已是完整结构
        }
        foreach ($this->linkRepository->listLibraryLinks() as $l) {
            $out[] = $this->item($l['label'], $l['path'], $l['category'], 'library');
        }
        return $out;
    }

    private function item(string $label, string $path, string $category, string $source): array
    {
        return [
            'label' => $label, 'path' => $path, 'category' => $category,
            'source' => $source, 'params_schema' => [],
            'external' => (bool) preg_match('#^https?://#i', $path),
        ];
    }
}
