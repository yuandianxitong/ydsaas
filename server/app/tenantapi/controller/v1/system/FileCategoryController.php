<?php

declare(strict_types=1);

namespace app\tenantapi\controller\v1\system;

use app\service\system\FileCategoryService;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use core\base\Controller;
use think\Response;

/**
 * 文件分类管理（素材库层级分类树）
 */
class FileCategoryController extends Controller
{
    protected FileCategoryService $fileCategoryService;

    /** 分类树（选择器/管理页共用，只读不设权限，对齐 file/groups 先例） */
    #[PermissionSkip]
    public function tree(): Response
    {
        return $this->success('', $this->fileCategoryService->getTree());
    }

    #[Permission('system.file-category.create')]
    public function create(): Response
    {
        $data = $this->request->param(['name', 'parent_id', 'sort']);

        return $this->success('', $this->fileCategoryService->create($data));
    }

    #[Permission('system.file-category.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $this->fileCategoryService->rename($id, (string) $this->request->param('name', ''));

        return $this->success();
    }

    #[Permission('system.file-category.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->fileCategoryService->delete($id);

        return $this->success();
    }
}
