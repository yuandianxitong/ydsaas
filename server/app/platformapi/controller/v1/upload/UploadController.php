<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\upload;

use app\service\system\FileService;
use core\attribute\Permission;
use core\base\Controller;
use core\storage\StorageManager;
use think\Response;
use think\facade\Filesystem;
use think\facade\Log;

class UploadController extends Controller
{
    protected FileService $fileService;

    /** 平台已种 platform.file.upload，上传走文件管理权限而非隐式放行 */
    #[Permission('platform.file.upload')]
    public function image(): Response
    {
        try {
            $file = $this->request->file('file');
            if (!$file) {
                return $this->error(lang('business.please_select_upload'));
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMime(), $allowedTypes)) {
                return $this->error(lang('business.upload_image_only'));
            }
            if ($file->getSize() > 2 * 1024 * 1024) {
                return $this->error(lang('business.file_size_2mb'));
            }
            return $this->handleUpload($file, 'uploads/images', 'images');
        } catch (\Exception $e) {
            return $this->error(sprintf(lang('business.upload_failed_reason'), $e->getMessage()));
        }
    }

    #[Permission('platform.file.upload')]
    public function file(): Response
    {
        try {
            $file = $this->request->file('file');
            if (!$file) {
                return $this->error(lang('business.please_select_upload'));
            }
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->error(lang('business.file_size_10mb'));
            }
            return $this->handleUpload($file, 'uploads/files', 'files');
        } catch (\Exception $e) {
            return $this->error(sprintf(lang('business.upload_failed_reason'), $e->getMessage()));
        }
    }

    private function handleUpload($file, string $directory, string $group): Response
    {
        // 平台端无配额限制，不调用 assertCanStore

        $extension = $file->extension();
        $filename = date('Ymd') . '/' . uniqid() . '.' . $extension;
        $storage = StorageManager::disk();
        $driverName = $storage->getDriver();

        if ($driverName === 'local') {
            $saveName = Filesystem::disk('public')->putFileAs($directory, $file, $filename);
            if (!$saveName) {
                return $this->error(lang('business.upload_failed'));
            }
            $relativePath = '/storage/' . str_replace('\\', '/', $saveName);
            $url = $this->request->domain() . $relativePath;
        } else {
            $remotePath = $directory . '/' . $filename;
            $tempPath = $file->getPathname();
            $url = $storage->upload($tempPath, $remotePath);
            $relativePath = $remotePath;
        }

        try {
            $this->fileService->recordFile([
                'name'      => $file->getOriginalName(),
                'path'      => $relativePath,
                'url'       => $url,
                'mime_type' => $file->getMime(),
                'extension' => $file->extension(),
                'size'      => $file->getSize(),
                'group'     => $group,
                'upload_by' => $this->getUserId(),
                'storage'   => $driverName,
            ]);
        } catch (\Exception $e) {
            Log::warning('Upload record failed: ' . $e->getMessage());
        }

        return $this->success(lang('messages.upload_success'), [
            'url'      => $url,
            'path'     => $relativePath,
            'filename' => $group === 'files' ? $file->getOriginalName() : $filename,
            'size'     => $file->getSize(),
            'storage'  => $driverName,
        ]);
    }
}
