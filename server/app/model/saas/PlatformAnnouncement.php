<?php
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class PlatformAnnouncement extends Model
{
    protected $name = 'platform_announcements';
    protected $autoWriteTimestamp = 'datetime';

    const TYPE_NOTICE  = 'notice';
    const TYPE_UPDATE  = 'update';
    const TYPE_WARNING = 'warning';

    const STATUS_DRAFT     = 'draft';
    const STATUS_PUBLISHED = 'published';

    protected $append = ['type_text', 'status_text'];

    public function getTypeTextAttr($value, $data): string
    {
        $typeMap = [
            self::TYPE_NOTICE  => '通知',
            self::TYPE_UPDATE  => '更新',
            self::TYPE_WARNING => '警告',
        ];
        return $typeMap[$data['type'] ?? ''] ?? '未知';
    }

    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_DRAFT     => '草稿',
            self::STATUS_PUBLISHED => '已发布',
        ];
        return $statusMap[$data['status'] ?? ''] ?? '未知';
    }
}
