<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\tenantapi\controller\v1\upload;

use app\service\saas\TenantQuotaService;
use app\service\system\FileService;
use core\base\Controller;
use core\storage\StorageManager;
use think\Response;
use think\facade\Filesystem;
use think\facade\Log;
use OpenApi\Attributes as OA;
use core\attribute\PermissionSkip;

#[OA\Tag(name: '文件上传', description: '图片和文件上传')]
class UploadController extends Controller
{
    protected FileService $fileService;
    protected TenantQuotaService $tenantQuotaService;

    #[OA\Post(
        path: '/upload/image',
        summary: '上传图片',
        security: [['bearerAuth' => []]],
        tags: ['文件上传'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: '图片文件（最大10MB，支持jpg/png/gif/webp）'),
                        new OA\Property(property: 'category_id', type: 'integer', description: '文件分类ID（可选，0=未分类）'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '上传成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '上传失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    // 公共能力：无独立 system.file.upload 种子；已认证租户管理员均可上传（素材/配置/头像等入口共用）
    #[PermissionSkip]
    public function image(): Response
    {
        try {
            $file = $this->request->file('file');
            if (!$file) {
                return $this->error(lang('business.please_select_upload'));
            }

            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMime(), $allowedTypes)) {
                return $this->error(lang('business.upload_image_only'));
            }

            // 验证文件大小 (10MB)
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->error(lang('business.file_size_10mb'));
            }

            $categoryId = (int) $this->request->param('category_id', 0);

            return $this->handleUpload($file, 'uploads/images', 'images', $categoryId);

        } catch (\Exception $e) {
            return $this->error(sprintf(lang('business.upload_failed_reason'), $e->getMessage()));
        }
    }

    #[OA\Post(
        path: '/upload/file',
        summary: '上传普通文件',
        security: [['bearerAuth' => []]],
        tags: ['文件上传'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: '文件（最大10MB）'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '上传成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '上传失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    // 同上：显式跳过细粒度权限，配合路由上的 admin_permission 形成「显式策略」而非隐式放行
    #[PermissionSkip]
    public function file(): Response
    {
        try {
            $file = $this->request->file('file');
            if (!$file) {
                return $this->error(lang('business.please_select_upload'));
            }

            // 验证文件大小 (10MB)
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->error(lang('business.file_size_10mb'));
            }

            return $this->handleUpload($file, 'uploads/files', 'files', 0);

        } catch (\Exception $e) {
            return $this->error(sprintf(lang('business.upload_failed_reason'), $e->getMessage()));
        }
    }

    /**
     * 统一上传处理
     */
    private function handleUpload($file, string $directory, string $group, int $categoryId = 0): Response
    {
        // M3C T96：上传前配额硬拦截。失败抛 BusinessException 由全局异常处理器转成标准错误响应。
        $this->tenantQuotaService->assertCanStore((int) $file->getSize());

        $extension = $file->extension();
        $filename = date('Ymd') . '/' . uniqid() . '.' . $extension;

        $storage = StorageManager::disk();
        $driverName = $storage->getDriver();

        if ($driverName === 'local') {
            // 本地存储：使用 ThinkPHP Filesystem
            $saveName = Filesystem::disk('public')->putFileAs($directory, $file, $filename);
            if (!$saveName) {
                return $this->error(lang('business.upload_failed'));
            }
            $relativePath = '/storage/' . str_replace('\\', '/', $saveName);
            $url = $this->getFullUrl($relativePath);
        } else {
            // 云存储：先保存临时文件，再上传到云端
            $remotePath = $directory . '/' . $filename;
            $tempPath = $file->getPathname();
            $url = $storage->upload($tempPath, $remotePath);
            $relativePath = $remotePath;
        }

        // 记录到文件管理
        $this->recordUpload($file, $relativePath, $url, $group, $driverName, $categoryId);

        return $this->success(lang('messages.upload_success'), [
            'url'      => $url,
            'path'     => $relativePath,
            'filename' => $group === 'files' ? $file->getOriginalName() : $filename,
            'size'     => $file->getSize(),
            'storage'  => $driverName,
        ]);
    }

    /**
     * 记录上传的文件到文件管理
     */
    private function recordUpload($file, string $path, string $url, string $group, string $storage = 'local', int $categoryId = 0): void
    {
        try {
            $this->fileService->recordFile([
                'name'      => $file->getOriginalName(),
                'path'      => $path,
                'url'       => $url,
                'mime_type' => $file->getMime(),
                'extension' => $file->extension(),
                'size'      => $file->getSize(),
                'group'     => $group,
                'category_id' => $categoryId,
                'upload_by' => $this->getUserId(),
                'storage'   => $storage,
            ]);
        } catch (\Exception $e) {
            Log::warning('Upload record failed: ' . $e->getMessage());
        }
    }

    /**
     * 获取完整的URL地址
     */
    private function getFullUrl(string $path): string
    {
        $request = $this->request;
        // host() 在 ThinkPHP 中已包含端口号（如 localhost:8005），无需再拼接
        return $request->domain() . $path;
    }
}
