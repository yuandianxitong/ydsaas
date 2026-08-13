<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use app\service\system\FileService;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use core\attribute\PermissionSkip;

class FileController extends Controller
{
    protected FileService $fileService;

    #[Permission('platform.file.list')]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->fileService->getFileList($params);
        return $this->paginate($result);
    }

    #[PermissionSkip]
    public function groups(): Response
    {
        $result = $this->fileService->getGroups();
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('platform.file.update')]
    public function moveGroup(): Response
    {
        $ids = $this->request->post('ids', []);
        $group = $this->request->post('group', '');
        if (empty($ids)) {
            return $this->error(lang('business.please_select_file'));
        }
        if (empty($group)) {
            return $this->error(lang('business.please_input_group'));
        }
        $this->fileService->moveToGroup($ids, $group);
        return $this->success(lang('messages.move_success'));
    }

    #[Permission('platform.file.update')]
    public function rename(): Response
    {
        $id = (int) $this->request->param('id');
        $name = $this->request->post('name', '');
        if (empty($name)) {
            return $this->error(lang('business.file_name_required'));
        }
        $this->fileService->renameFile($id, $name);
        return $this->success(lang('messages.rename_success'));
    }

    #[Permission('platform.file.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->fileService->deleteFile($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('platform.file.delete')]
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids', []);
        if (empty($ids)) {
            return $this->error(lang('business.please_select_files'));
        }
        $count = $this->fileService->batchDelete($ids);
        return $this->success(sprintf(lang('messages.file_delete_count'), $count));
    }
}
