<?php
/* ============================================================
 * 项目：元点系统
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
namespace app\index\controller;

use app\BaseController;
use think\facade\Config;

class Index extends BaseController
{
    public function index()
    {
        $path = (string) (parse_url(request()->url(true), PHP_URL_PATH) ?: '/');
        // 已是前台入口仍落到 PHP：多半是 nginx 静态 location 未生效或 try_files
        // 回退到了 /index.html→index.php。禁止再 302，否则移动端会 /mobile/ 死循环。
        if (preg_match('#^/(mobile|pc|platform)(/|$)#', $path) === 1) {
            return response(
                'Static entry not served by nginx for ' . $path
                . '. Ensure location ^~ /mobile/ (or /pc/) serves public files and SPA fallback does not hit index.php.',
                404,
            );
        }

        $host = strtolower(request()->host(true));
        $platformDomains = array_map(
            'strtolower',
            (array) Config::get('saas.platform_domains', [])
        );

        // 平台域名的根路径直接进入平台管理端，避免落到租户 PC 门户。
        if (in_array($host, $platformDomains, true)) {
            return redirect('/platform/');
        }

        $ua = request()->header('user-agent', '');
        $isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini|IEMobile/i', $ua);

        // 仅站点根路径按 UA 分流；PC→/pc/、移动→/mobile/ 是预期行为。
        return redirect($isMobile ? '/mobile/' : '/pc/');
    }
}
