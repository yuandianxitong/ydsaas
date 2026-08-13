<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\user;

use think\facade\Log;

/**
 * 用户登录监听器
 *
 * 事件数据：
 * - user_id: int  用户ID
 *
 * 产品状态（未实现，勿当作已交付能力）：
 * - 登录积分 / 签到奖励：无对应服务与配置，本监听器仅打日志
 * - 登录统计：仪表盘走独立查询，不依赖此处
 */
class UserLoginListener
{
    public function handle(array $event): void
    {
        Log::info('用户登录', ['user_id' => $event['user_id']]);
    }
}
