<?php

/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\exception;

use Exception;

class BusinessException extends Exception
{
    protected $data = [];

    public function __construct(string $message = '', int $code = 400, array $data = [], ?Exception $previous = null)
    {
        $this->data = $data;
        parent::__construct($message ?: lang('auth.business_error'), $code, $previous);
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 插件运行期错误（hook 抛出 / loader 失败 / 校验失败）。
     */
    public static function pluginRuntimeError(string $pluginCode, string $hook, ?\Throwable $previous = null): self
    {
        $msg = "插件 [{$pluginCode}] 运行 [{$hook}] 失败"
             . ($previous ? '：' . $previous->getMessage() : '');
        return new self($msg, 50001, ['plugin_code' => $pluginCode, 'hook' => $hook], $previous instanceof \Exception ? $previous : null);
    }
}
