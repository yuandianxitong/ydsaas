<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class TenantMobileBuild extends Model
{
    protected $name = 'tenant_mobile_builds';
    protected $autoWriteTimestamp = 'datetime';

    /** 本表无 deleted_at 列。 */
    protected $deleteTime = false;

    protected $type = [
        'included_plugins_json' => 'json',
        'pages_json'            => 'json',
        'manifest_json'         => 'json',
        'upload_result_json'    => 'json',
        'runtime_json'          => 'json',
    ];

    public const STATUS_QUEUED   = 0;
    public const STATUS_RUNNING  = 1;
    public const STATUS_SUCCESS  = 2;
    public const STATUS_FAILED   = 3;
    public const STATUS_UPLOADED = 4;
    public const STATUS_RELEASED = 5;

    public const PLATFORMS = ['h5', 'mp-weixin', 'app'];
}
