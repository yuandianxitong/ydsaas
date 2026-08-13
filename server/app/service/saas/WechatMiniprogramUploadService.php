<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\service\saas;

use app\model\saas\TenantMobileBuild;
use app\repository\saas\TenantMobileBuildRepository;
use app\repository\saas\TenantMobileConfigRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\mobile\MobileBuildEnv;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * 微信小程序上传开发版（基于 miniprogram-ci）。
 *
 * 流程：
 *   1. WechatUploadKeyService 解密 .key 写临时文件
 *   2. 调用本地 miniprogram-ci CLI（require：npx 或全局已装 miniprogram-ci）
 *      命令模板（注意：--version 是 CLI 自身版本开关，上传版本必须用 --uv）：
 *        miniprogram-ci upload \
 *          --project-path  <artifact_path>           \
 *          --appid         <wechat_appid>            \
 *          --private-key-path <tmp.key>              \
 *          --uv            <wechat_upload_version>   \
 *          --ud            <wechat_upload_desc>
 *   3. 校验输出：拒绝仅打印 CLI 版本号的假成功
 *   4. 写 upload_result_json + 状态 success → uploaded；版本号 patch +1
 *   5. 删 .key 临时文件（finally）
 *
 * 注意：上传后出现在微信公众平台「版本管理 → 开发版本」；设为体验版需在微信后台手工操作。
 * 机器人编号沿用 miniprogram-ci 默认（1），不在此配置。
 */
final class WechatMiniprogramUploadService extends Service
{
    private const DEFAULT_TIMEOUT_SEC = 600;
    private const DEFAULT_UPLOAD_DESC = '租户后台发布';

    protected TenantMobileBuildRepository $buildRepo;
    protected TenantMobileConfigRepository $configRepo;
    protected WechatUploadKeyService $keyService;

    public function upload(int $tenantId, int $buildId): array
    {
        $build = $this->buildRepo->findByTenantAndId($tenantId, $buildId);
        if (!$build) {
            throw new BusinessException('build not found', 404);
        }
        if ((string) $build['platform'] !== 'mp-weixin') {
            throw new BusinessException('only mp-weixin builds can be uploaded', 422);
        }
        $status = (int) $build['status'];
        // failed 也允许重试（产物仍在时）；uploaded 允许覆盖上传
        if (
            $status !== TenantMobileBuild::STATUS_SUCCESS
            && $status !== TenantMobileBuild::STATUS_UPLOADED
            && $status !== TenantMobileBuild::STATUS_FAILED
        ) {
            throw new BusinessException("build status must be success/uploaded/failed, got {$status}", 422);
        }

        $artifact = (string) $build['artifact_path'];
        if ($artifact === '' || !is_dir($artifact)) {
            throw new BusinessException("artifact dir not found: {$artifact}", 422);
        }

        $cfg = $this->configRepo->findByTenantId($tenantId);
        $appid = (string) ($cfg['wechat_appid'] ?? '');
        if ($appid === '') {
            throw new BusinessException('该租户未配置 wechat_appid', 422);
        }
        if (empty($cfg['wechat_upload_key_ciphertext'] ?? null)) {
            throw new BusinessException('该租户未上传小程序私钥', 422);
        }

        $uploadVersion = trim((string) ($cfg['wechat_upload_version'] ?? ''));
        if ($uploadVersion === '' || !preg_match('/^\d+\.\d+\.\d+$/', $uploadVersion)) {
            throw new BusinessException('请先在「微信小程序上传配置」填写合法版本号（如 1.0.3）', 422);
        }
        $uploadDesc = trim((string) ($cfg['wechat_upload_desc'] ?? ''));
        if ($uploadDesc === '') {
            $uploadDesc = self::DEFAULT_UPLOAD_DESC;
        }

        $ci = (new ExecutableFinder())->find('miniprogram-ci')
            ?? (new ExecutableFinder())->find('npx');
        if ($ci === null) {
            throw new BusinessException('miniprogram-ci 或 npx 不在 PATH 中', 500);
        }
        $useNpx = str_ends_with($ci, 'npx');

        $keyPath = $this->keyService->exportToTempFile($tenantId);
        try {
            $cmd = $useNpx
                ? [$ci, '--yes', 'miniprogram-ci', 'upload']
                : [$ci, 'upload'];
            // miniprogram-ci：--version 是打印 CLI 版本；上传版本/描述必须用 --uv / --ud
            $cmd = array_merge($cmd, [
                '--project-path',     $artifact,
                '--appid',            $appid,
                '--private-key-path', $keyPath,
                '--uv',               $uploadVersion,
                '--ud',               $uploadDesc,
            ]);

            $timeout = MobileBuildEnv::getInt('MOBILE_BUILD_TIMEOUT_SEC', self::DEFAULT_TIMEOUT_SEC);
            $proc = new Process($cmd, null, null, null, $timeout);
            $proc->run();
            $stdout = $proc->getOutput();
            $stderr = $proc->getErrorOutput();
            $log = "== miniprogram-ci upload ==\n{$stdout}\n{$stderr}";
            $stdoutTrim = trim($stdout);
            $looksLikeVersionOnly = (bool) preg_match('/^\d+\.\d+\.\d+$/', $stdoutTrim);

            // --version 会被 CLI 当成「打印自身版本」并 exit 0；禁止把这种输出当成上传成功
            if ($looksLikeVersionOnly) {
                $this->buildRepo->markStatus($buildId, TenantMobileBuild::STATUS_FAILED, [
                    'error_log' => \core\mobile\ErrorLogSanitizer::fromLog(
                        $log . "\n[saas] rejected: stdout is CLI version only, upload did not run",
                    ),
                ]);
                throw new BusinessException(
                    '小程序上传未执行：miniprogram-ci 仅返回了 CLI 版本号（请检查 --uv 参数）',
                    500,
                );
            }

            if (!$proc->isSuccessful()) {
                $wechatHint = self::extractWechatUploadError($stdout . "\n" . $stderr);
                $this->buildRepo->markStatus($buildId, TenantMobileBuild::STATUS_FAILED, [
                    'error_log' => \core\mobile\ErrorLogSanitizer::fromLog($log),
                ]);
                throw new BusinessException(
                    $wechatHint !== ''
                        ? $wechatHint
                        : "小程序上传失败（exit {$proc->getExitCode()}）",
                    500,
                );
            }

            $nextVersion = self::bumpPatchVersion($uploadVersion);
            $this->configRepo->upsert($tenantId, [
                'wechat_upload_version' => $nextVersion,
            ]);

            $result = [
                'appid'          => $appid,
                'version'        => $uploadVersion,
                'desc'           => $uploadDesc,
                'next_version'   => $nextVersion,
                'build_no'       => (string) $build['build_no'],
                'uploaded_at'    => date('Y-m-d H:i:s'),
                'stdout_excerpt' => mb_substr(\core\mobile\ErrorLogSanitizer::fromLog($stdout), 0, 4096),
            ];
            // 上传后为「开发版本」；设体验版需在微信公众平台手工操作
            $this->buildRepo->markStatus($buildId, TenantMobileBuild::STATUS_UPLOADED, [
                'upload_result_json' => $result,
                'error_log'          => \core\mobile\ErrorLogSanitizer::fromLog($log),
                'finished_at'        => date('Y-m-d H:i:s'),
            ]);

            return $this->buildRepo->findByTenantAndId($tenantId, $buildId) ?? [];
        } finally {
            $this->keyService->dropTemp($keyPath);
        }
    }

    /**
     * 上传成功后 patch +1：1.0.3 → 1.0.4
     */
    private static function bumpPatchVersion(string $version): string
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $m)) {
            return $version;
        }
        return $m[1] . '.' . $m[2] . '.' . ((int) $m[3] + 1);
    }

    /**
     * 从 miniprogram-ci 输出提取微信侧可读错误（如 IP 白名单）。
     */
    private static function extractWechatUploadError(string $output): string
    {
        if (preg_match('/"errMsg"\s*:\s*"([^"]+)"/', $output, $m)) {
            $errMsg = stripcslashes($m[1]);
            if (preg_match('/invalid ip:\s*([0-9.]+)/i', $errMsg, $ipM)) {
                return "小程序上传失败：IP {$ipM[1]} 未加入微信「代码上传」IP 白名单。"
                    . '请到微信公众平台 → 开发管理 → 开发设置 → 小程序代码上传 → 将本机出口 IP 加入白名单后重试';
            }
            return '小程序上传失败：' . $errMsg;
        }
        if (preg_match('/\[error\]\s+\d+\s+Error:\s*(\{.*\})/s', $output, $m)) {
            return '小程序上传失败：' . mb_substr($m[1], 0, 500);
        }
        return '';
    }
}
