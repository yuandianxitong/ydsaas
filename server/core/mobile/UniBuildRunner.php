<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * 调用 pnpm + uni build 实际生成产物。
 *
 *   1. 在 $uniappDir 跑 `pnpm install --prefer-offline --no-frozen-lockfile`
 *   2. 跑 `pnpm exec uni build`（h5）或 `pnpm exec uni build -p {platform}`
 *   3. 收集 stdout + stderr 合并成 log
 *   4. 返回最终产物路径（uniapp/dist/build/h5 等）
 *
 * 超时由 .env 的 MOBILE_BUILD_TIMEOUT_SEC 控制，默认 600 秒。
 */
class UniBuildRunner
{
    private const DEFAULT_TIMEOUT_SEC = 600;

    /**
     * @return array{success: bool, log: string, artifactPath: string}
     */
    public function run(string $uniappDir, string $platform): array
    {
        $platformDir = $this->platformDirName($platform);
        if ($platformDir === null) {
            return [
                'success'      => false,
                'log'          => "[runner] unknown platform: {$platform}",
                'artifactPath' => '',
            ];
        }

        $pnpm = (new ExecutableFinder())->find('pnpm');
        if ($pnpm === null) {
            return [
                'success'      => false,
                'log'          => '[runner] pnpm not found in PATH',
                'artifactPath' => '',
            ];
        }

        $timeout = MobileBuildEnv::getInt('MOBILE_BUILD_TIMEOUT_SEC', self::DEFAULT_TIMEOUT_SEC);
        $env = ['CI' => 'true'];

        $log = '';

        // 1. pnpm install
        $install = new Process(
            [$pnpm, 'install', '--prefer-offline', '--no-frozen-lockfile'],
            $uniappDir,
            $env,
            null,
            $timeout,
        );
        $install->run();
        $log .= "== pnpm install ==\n" . $install->getOutput() . $install->getErrorOutput() . "\n";

        if (!$install->isSuccessful()) {
            return [
                'success'      => false,
                'log'          => $log . "\n[runner] pnpm install failed (exit {$install->getExitCode()})",
                'artifactPath' => '',
            ];
        }

        // 2. 直接调用 Uni CLI，避免触发 package.json 的 prebuild:* 开发态同步脚本。
        $buildCommand = $this->buildCommand($pnpm, $platform);
        if ($buildCommand === null) {
            return [
                'success'      => false,
                'log'          => $log . "\n[runner] unsupported build command platform: {$platform}",
                'artifactPath' => '',
            ];
        }
        $build  = new Process(
            $buildCommand,
            $uniappDir,
            $env,
            null,
            $timeout,
        );
        $build->run();
        $log .= "== " . implode(' ', $buildCommand) . " ==\n" . $build->getOutput() . $build->getErrorOutput() . "\n";

        if (!$build->isSuccessful()) {
            return [
                'success'      => false,
                'log'          => $log . "\n[runner] uni build {$platform} failed (exit {$build->getExitCode()})",
                'artifactPath' => '',
            ];
        }

        $artifact = $uniappDir . '/dist/build/' . $platformDir;
        if (!is_dir($artifact)) {
            return [
                'success'      => false,
                'log'          => $log . "\n[runner] artifact dir not found after build: {$artifact}",
                'artifactPath' => '',
            ];
        }

        return [
            'success'      => true,
            'log'          => $log,
            'artifactPath' => $artifact,
        ];
    }

    private function platformDirName(string $platform): ?string
    {
        return match ($platform) {
            'h5'        => 'h5',
            'mp-weixin' => 'mp-weixin',
            'app'       => 'app',
            default     => null,
        };
    }

    /**
     * @return list<string>|null
     */
    private function buildCommand(string $pnpm, string $platform): ?array
    {
        return match ($platform) {
            'h5'        => [$pnpm, 'exec', 'uni', 'build'],
            'mp-weixin' => [$pnpm, 'exec', 'uni', 'build', '-p', 'mp-weixin'],
            'app'       => [$pnpm, 'exec', 'uni', 'build', '-p', 'app'],
            default     => null,
        };
    }
}
