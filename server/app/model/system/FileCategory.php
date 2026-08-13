<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

/**
 * 文件分类模型
 */
class FileCategory extends Model
{
    protected $name = 'file_categories';

    // file_categories 表无 deleted_at 列，禁用基类默认的软删除（参照 app/model/region/Region.php）
    protected $deleteTime = false;

    protected $fillable = ['parent_id', 'name', 'sort'];
}
