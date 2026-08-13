<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\base;

use think\Model;
use think\Collection;
use think\db\BaseQuery;
use core\exception\BusinessException;
use core\tenant\TenantContext;

abstract class Repository
{
    protected Model $model;

    /**
     * 是否启用 tenant 作用域。
     * 默认 true，少数特殊 Repository（如 SaasOrderRepository）可以关闭。
     */
    protected bool $tenantScoped = true;

    public function __construct()
    {
        $this->model = $this->getModel();
    }

    /**
     * 获取模型实例
     */
    abstract protected function getModel(): Model;

    /**
     * ⭐ Repository 子类必须用此方法获取查询构造器，禁止 $this->model->where() 起手。
     *
     * 自动注入 where tenant_id = ctx->id()，除非：
     *   1. 当前 Repository 关闭了 tenantScoped
     *   2. 当前正处于 TenantContext::runWithoutScope() 内
     *   3. TenantContext 未设置（CLI 命令等场景）
     *
     * @param string $alias 主表别名。join 查询必须传（且不要再手写 ->alias()）：
     *                      被 join 的表通常同样有 tenant_id 列，自动注入的过滤不带
     *                      表前缀时会触发 SQLSTATE 1052（Column ambiguous）。
     */
    protected function query(string $alias = ''): BaseQuery
    {
        $query = $this->model->db();
        if ($alias !== '') {
            $query->alias($alias);
        }

        if ($this->shouldApplyTenantScope()) {
            $ctx = TenantContext::current();
            if ($ctx !== null) {
                $query->where(($alias !== '' ? $alias . '.' : '') . 'tenant_id', $ctx->id());
            }
        }

        return $query;
    }

    /**
     * 子类可重写此方法实现自定义判定（极少需要）。
     */
    protected function shouldApplyTenantScope(): bool
    {
        if (!$this->tenantScoped) {
            return false;
        }
        if (TenantContext::isScopeBypassed()) {
            return false;
        }
        return true;
    }

    /**
     * 查找单个记录
     */
    public function find($id): ?array
    {
        $result = $this->query()->where('id', $id)->find();
        return $result ? (is_array($result) ? $result : $result->toArray()) : null;
    }

    /**
     * 根据条件查找单个记录
     */
    public function findWhere(array $where): ?array
    {
        $result = $this->query()->where($where)->find();
        return $result ? (is_array($result) ? $result : $result->toArray()) : null;
    }

    /**
     * 创建记录
     *
     * 写入时自动填充 tenant_id（如果 data 中没传 + 当前作用域生效）。
     */
    public function create(array $data): array
    {
        try {
            if ($this->shouldApplyTenantScope() && !isset($data['tenant_id'])) {
                $ctx = TenantContext::current();
                if ($ctx !== null) {
                    $data['tenant_id'] = $ctx->id();
                }
            }
            $result = $this->model->create($data);
            return $result->toArray();
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.create_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 批量创建
     */
    public function createAll(array $data): Collection
    {
        try {
            if ($this->shouldApplyTenantScope()) {
                $ctx = TenantContext::current();
                if ($ctx !== null) {
                    foreach ($data as $i => $row) {
                        if (!isset($row['tenant_id'])) {
                            $data[$i]['tenant_id'] = $ctx->id();
                        }
                    }
                }
            }
            return $this->model->saveAll($data);
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.batch_create_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 更新记录
     */
    public function update($id, array $data): bool
    {
        try {
            return $this->query()->where('id', $id)->update($data) > 0;
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.update_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 根据条件更新
     */
    public function updateWhere(array $where, array $data): int
    {
        try {
            return $this->query()->where($where)->update($data);
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.update_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 删除记录。
     *
     * 走 Model 实例的 delete()，让 SoftDelete trait 能正确触发软删除；
     * Query::delete() 不感知软删除会静默 no-op。
     * 仍通过 query() 命中，确保 tenant scope 不被绕过。
     */
    public function delete($id): bool
    {
        try {
            $instance = $this->query()->where('id', $id)->find();
            if (!$instance) {
                return false;
            }
            return (bool) $instance->delete();
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.delete_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 根据条件删除
     */
    public function deleteWhere(array $where): int
    {
        try {
            return $this->query()->where($where)->delete();
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.delete_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取列表
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15, string $order = 'id desc'): array
    {
        $query = $this->query()->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)->order($order)->select()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 构造统一的分页响应结构
     *
     * 用于 Repository 的自定义 `getSearchList` 方法：当查询条件复杂无法直接复用 `getList` 时，
     * 各子类只需查询出 $list 和 $total，然后调用此方法返回统一结构，避免到处手写 `pagination` 数组。
     */
    protected function buildPagination(array $list, int $page, int $limit, int $total): array
    {
        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => $limit > 0 ? (int) ceil($total / $limit) : 1,
            ],
        ];
    }

    /**
     * 获取所有记录
     */
    public function getAll(array $where = [], string $order = 'id desc'): array
    {
        return $this->query()->where($where)->order($order)->select()->toArray();
    }

    /**
     * 统计记录数
     */
    public function count(array $where = []): int
    {
        return $this->query()->where($where)->count();
    }

    /**
     * 检查记录是否存在
     */
    public function exists(array $where): bool
    {
        return $this->query()->where($where)->count() > 0;
    }

    /**
     * 获取字段值
     */
    public function value(array $where, string $field)
    {
        return $this->query()->where($where)->value($field);
    }

    /**
     * 获取字段列表
     */
    public function column(array $where, string $field, string $key = ''): array
    {
        return $this->query()->where($where)->column($field, $key);
    }

    /**
     * 字段自增
     */
    public function inc(array $where, string $field, int $step = 1): int
    {
        return $this->query()->where($where)->inc($field, $step)->update();
    }

    /**
     * 字段自减
     */
    public function dec(array $where, string $field, int $step = 1): int
    {
        return $this->query()->where($where)->dec($field, $step)->update();
    }
}
