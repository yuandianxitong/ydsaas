<?php
declare(strict_types=1);

namespace app\tenantapi\controller\v1\system;

use app\service\system\FileService;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '文件管理', description: '文件的列表、分类、重命名、删除')]
class FileController extends Controller
{
    protected FileService $fileService;

    #[Permission('system.file.list')]
    #[OA\Get(
        path: '/system/file',
        summary: '文件列表',
        security: [['bearerAuth' => []]],
        tags: ['文件管理'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'query', description: '文件分组', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', description: '文件分类ID（缺省=全部，0=未分类，>0=指定分类直属）', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->fileService->getFileList($params);

        return $this->paginate($result);
    }

    #[Permission('system.file.update')]
    #[OA\Put(
        path: '/system/file/{id}/rename',
        summary: '重命名文件',
        security: [['bearerAuth' => []]],
        tags: ['文件管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '文件ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: '新文件名'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '重命名成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
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

    #[Permission('system.file.delete')]
    #[OA\Delete(
        path: '/system/file/{id}',
        summary: '删除文件',
        security: [['bearerAuth' => []]],
        tags: ['文件管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '文件ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->fileService->deleteFile($id);

        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.file.delete')]
    #[OA\Post(
        path: '/system/file/batch-delete',
        summary: '批量删除文件',
        security: [['bearerAuth' => []]],
        tags: ['文件管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), description: '文件ID列表'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '批量删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids', []);

        if (empty($ids)) {
            return $this->error(lang('business.please_select_files'));
        }

        $count = $this->fileService->batchDelete($ids);

        return $this->success(sprintf(lang('messages.file_delete_count'), $count));
    }

    #[Permission('system.file.update')]
    #[OA\Post(
        path: '/system/file/move-category',
        summary: '批量移动文件到分类',
        security: [['bearerAuth' => []]],
        tags: ['文件管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids', 'category_id'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), description: '文件ID列表'),
                    new OA\Property(property: 'category_id', type: 'integer', description: '目标分类ID（0=未分类）'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '移动成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function moveCategory(): Response
    {
        $ids = (array) $this->request->param('ids', []);
        $categoryId = (int) $this->request->param('category_id', 0);
        $this->fileService->moveToCategory($ids, $categoryId);

        return $this->success();
    }
}
