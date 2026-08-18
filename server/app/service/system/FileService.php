<?php
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\FileCategoryRepository;
use app\repository\system\FileRepository;
use app\service\saas\TenantQuotaService;
use core\base\Service;
use core\storage\StorageManager;
use core\exception\BusinessException;
use think\facade\Log;

class FileService extends Service
{
    protected FileRepository $fileRepo;
    protected FileCategoryRepository $fileCategoryRepo;
    protected TenantQuotaService $tenantQuotaService;

    /**
     * 获取文件列表
     */
    public function getFileList(array $params): array
    {
        $where = [];

        if (!empty($params['keyword'])) {
            $where[] = ['name', 'like', '%' . $params['keyword'] . '%'];
        }
        if (!empty($params['group'])) {
            $where[] = ['group', '=', $params['group']];
        }
        if (!empty($params['mime_type'])) {
            $this->applyMimeTypeFilter($where, (string) $params['mime_type']);
        }
        if (isset($params['category_id']) && $params['category_id'] !== '') {
            $where[] = ['category_id', '=', (int) $params['category_id']];
        }

        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);

        return $this->fileRepo->getFileList($where, $page, $limit);
    }

    /**
     * 记录上传的文件
     */
    public function recordFile(array $data): array
    {
        $size = (int) ($data['size'] ?? 0);
        $record = $this->fileRepo->create([
            'name'      => $data['name'],
            'path'      => $data['path'],
            'url'       => $data['url'],
            'mime_type' => $data['mime_type'],
            'extension' => $data['extension'],
            'size'      => $size,
            'group'     => $data['group'] ?? '默认',
            'category_id' => (int) ($data['category_id'] ?? 0),
            'upload_by' => $data['upload_by'] ?? 0,
            'storage'   => $data['storage'] ?? 'local',
        ]);

        // M3C T96：配额 +=（平台端不计配额）
        if ($size > 0 && !$this->isPlatformContext()) {
            $this->tenantQuotaService->consume($size);
        }

        return $record;
    }

    /**
     * 移动到分组
     */
    public function moveToGroup(array $ids, string $group): bool
    {
        foreach ($ids as $id) {
            $this->fileRepo->update((int)$id, ['group' => $group]);
        }
        return true;
    }

    /** 批量移动文件到分类（0=未分类） */
    public function moveToCategory(array $ids, int $categoryId): void
    {
        if ($categoryId > 0 && !$this->fileCategoryRepo->find($categoryId)) {
            throw new BusinessException('目标分类不存在');
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }
        $this->runInTransaction(function () use ($ids, $categoryId) {
            $this->fileRepo->moveToCategory($ids, $categoryId);
        });
    }

    /**
     * 删除文件
     */
    public function deleteFile(int $id): bool
    {
        $file = $this->fileRepo->find($id);
        if (!$file) {
            throw new BusinessException(lang('business.file_not_found'));
        }

        $size = (int) ($file['size'] ?? 0);
        $path = $file['path'];
        $storageType = $file['storage'] ?? 'local';

        if ($path) {
            try {
                $storage = StorageManager::disk($storageType);
                $storage->delete($path);
            } catch (\Throwable $e) {
                // 物理文件删除失败不阻断数据库记录删除，但记录日志以便运维排查
                Log::warning('物理文件删除失败', [
                    'file_id' => $id,
                    'path'    => $path,
                    'storage' => $storageType,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $deleted = $this->fileRepo->delete($id);

        // M3C T96：配额 -=（只在 DB 记录真的被删掉后才释放，平台端不计配额）
        if ($deleted && $size > 0 && !$this->isPlatformContext()) {
            $this->tenantQuotaService->release($size);
        }

        return $deleted;
    }

    /**
     * 批量删除
     */
    public function batchDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            try {
                $this->deleteFile((int)$id);
                $count++;
            } catch (\Exception $e) {
                Log::warning('File delete failed: ' . $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * 获取分组列表
     */
    public function getGroups(): array
    {
        return $this->fileRepo->getGroups();
    }

    /**
     * 重命名文件
     */
    public function renameFile(int $id, string $name): bool
    {
        $file = $this->fileRepo->find($id);
        if (!$file) {
            throw new BusinessException(lang('business.file_not_found'));
        }
        return $this->fileRepo->update($id, ['name' => $name]);
    }

    /**
     * mime_type 过滤：image / other 保持旧语义；新增 video / audio / document / archive / misc。
     */
    private function applyMimeTypeFilter(array &$where, string $mimeType): void
    {
        $documents = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md'];
        $archives  = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'];

        switch ($mimeType) {
            case 'image':
                $where[] = ['mime_type', 'like', 'image/%'];
                break;
            case 'video':
                $where[] = ['mime_type', 'like', 'video/%'];
                break;
            case 'audio':
                $where[] = ['mime_type', 'like', 'audio/%'];
                break;
            case 'document':
                $where[] = ['extension', 'in', $documents];
                break;
            case 'archive':
                $where[] = ['extension', 'in', $archives];
                break;
            case 'misc':
                $where[] = ['mime_type', 'not like', 'image/%'];
                $where[] = ['mime_type', 'not like', 'video/%'];
                $where[] = ['mime_type', 'not like', 'audio/%'];
                $where[] = ['extension', 'not in', array_merge($documents, $archives)];
                break;
            case 'other':
            default:
                $where[] = ['mime_type', 'not like', 'image/%'];
                break;
        }
    }

    /**
     * 判断当前是否为平台上下文
     */
    private function isPlatformContext(): bool
    {
        $ctx = \core\tenant\TenantContext::current();
        return $ctx !== null && $ctx->isPlatform();
    }
}
