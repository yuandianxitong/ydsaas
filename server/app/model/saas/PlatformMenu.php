<?php
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;
use think\model\relation\HasMany;

class PlatformMenu extends Model
{
    protected $name = 'platform_menus';
    protected $autoWriteTimestamp = 'datetime';

    // type: 1=directory, 2=menu, 3=button

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
