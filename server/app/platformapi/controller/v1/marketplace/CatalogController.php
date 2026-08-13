<?php

declare(strict_types=1);

namespace app\platformapi\controller\v1\marketplace;

use app\repository\marketplace\MarketplaceConnectionRepository;
use app\service\marketplace\MarketplaceCatalogService;
use app\service\marketplace\OfficialMarketplaceClient;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

class CatalogController extends Controller
{
    protected MarketplaceConnectionRepository $connRepo;
    protected MarketplaceCatalogService $catalog;
    protected OfficialMarketplaceClient $client;

    #[Permission('marketplace.catalog.view')]
    public function apps(): Response
    {
        $conn = $this->connRepo->findActive();
        $defaultSite = rtrim((string) config('saas.marketplace.default_site_base_url', 'https://www.dev007.cn'), '/');

        $page  = max(1, (int) $this->request->get('page', 1));
        $limit = (int) $this->request->get('limit', 20);
        $limit = max(1, min(100, $limit)); // 防御异常 limit

        if (!$conn) {
            // 未绑定 Site: 拉公开 catalog 给浏览态展示
            $catalogError = null;
            $categoryId = (int) $this->request->get('category_id', 0);
            try {
                $public = $this->client->publicCatalog($defaultSite, $categoryId > 0 ? $categoryId : null);
            } catch (\Throwable $e) {
                $public = [];
                $catalogError = $e->getMessage();
            }
            $total = count($public);
            $pageItems = array_slice($public, ($page - 1) * $limit, $limit);
            $merged = array_map(fn ($app) => [
                'entitlement_code'  => null,
                'remote_app_id'     => (string) ($app['id']             ?? $app['code'] ?? ''),
                'app_code'          => (string) ($app['code']           ?? ''),
                'app_name'          => (string) ($app['name']           ?? ''),
                // Site 字段: summary 是短描述, description 是长描述; 取非空者
                'app_description'   => (string) ($app['summary'] ?: ($app['description'] ?? '')),
                // Site 用 icon, B-2 内部统一 app_icon_url
                'app_icon_url'      => (string) ($app['icon']           ?? $app['icon_url'] ?? ''),
                'publisher_name'    => (string) ($app['publisher_name'] ?? 'official'),
                'latest_version'    => (string) ($app['latest_version'] ?? ''),
                'latest_version_id' => '',
                'installed'         => false,
                'installed_version' => null,
                'plugin_id'         => null,
                'has_upgrade'       => false,
                'is_public'         => true,
                'category_id'       => $app['category_id'] ?? null,
                'category'          => $app['category'] ?? null,
            ], $pageItems);
            return $this->success('ok', [
                'data'           => $merged,
                'connection_id'  => null,
                'is_public'      => true,
                'site_base_url'  => $defaultSite,
                'catalog_error'  => $catalogError,
                'pagination'     => ['total' => $total, 'page' => $page, 'limit' => $limit],
            ]);
        }

        $categoryId = (int) $this->request->get('category_id', 0);
        $merged = $this->catalog->mergedCatalog($conn, $categoryId > 0 ? $categoryId : null);
        $total  = count($merged);
        $pageItems = array_slice($merged, ($page - 1) * $limit, $limit);

        return $this->success('ok', [
            'data'          => $pageItems,
            'connection_id' => (int) $conn['id'],
            'is_public'     => false,
            'site_base_url' => (string) $conn['site_base_url'],
            'pagination'    => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ]);
    }

    #[Permission('marketplace.catalog.view')]
    public function categories(): Response
    {
        $conn = $this->connRepo->findActive();
        $defaultSite = rtrim((string) config('saas.marketplace.default_site_base_url', 'https://www.dev007.cn'), '/');
        $base = $conn ? (string) $conn['site_base_url'] : $defaultSite;
        try {
            $list = $this->client->publicCategories($base);
        } catch (\Throwable $e) {
            $list = [];
        }
        return $this->success('ok', ['data' => $list]);
    }

    #[Permission('marketplace.connection.manage')]
    public function sync(): Response
    {
        $conn = $this->connRepo->findActive();
        if (!$conn) {
            throw new BusinessException('未绑定 Site', 422);
        }
        return $this->success('ok', $this->catalog->syncConnection($conn));
    }
}
