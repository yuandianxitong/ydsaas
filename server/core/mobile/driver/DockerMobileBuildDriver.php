<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile\driver;

use core\mobile\MobileBuildContext;
use core\mobile\MobileBuildDriverResult;
use core\mobile\MobileBuildEnv;
use Symfony\Component\Process\Process;

/**
 * 容器构建：把已准备好的 workspace 挂进 UniApp 构建镜像，在容器内跑
 * pnpm install + uni build。产物落在挂载目录 {uniappDir}/dist/build/{platformDir}，
 * 与 LocalMobileBuildDriver 的本地目录契约一致。
 *
 * 命令模型（design §2.3 / §3.3）：
 *   docker run --rm -v {workspaceDir}:/workspace -w /workspace/uniapp \
 *     [--memory MEM] [--cpus CPUS] [--network NET] {IMAGE} \
 *     sh -lc "CI=true pnpm install ... && CI=true pnpm exec uni build [-p platform]"
 *
 * 平台支持与 UniBuildRunner 对齐（h5/mp-weixin/app）；app 仅产出资源，非云打包。
 *
 * 注意：非 final——测试通过匿名子类覆盖 protected runDocker() 注入 fake。
 */
class DockerMobileBuildDriver implements MobileBuildDriver
{
    private const DEFAULT_TIMEOUT_SEC = 900;

    public function build(MobileBuildContext $ctx): MobileBuildDriverResult
    {
        $platformDir = $this->platformDirName($ctx->platform);
        if ($platformDir === null) {
            return $this->fail("[docker] unknown platform: {$ctx->platform}", null);
        }

        $image = MobileBuildEnv::get('MOBILE_BUILD_DOCKER_IMAGE', '') ?? '';
        if ($image === '') {
            return $this->fail('[docker] MOBILE_BUILD_DOCKER_IMAGE not configured', null);
        }

        $command = $this->buildCommand($ctx, $image, $platformDir);
        $timeout = MobileBuildEnv::getInt('MOBILE_BUILD_TIMEOUT_SEC', self::DEFAULT_TIMEOUT_SEC);

        $run = $this->runDocker($command, $ctx->workspaceDir, $timeout);
        $log = '== ' . implode(' ', $command) . " ==\n" . $run['output'] . "\n";

        if ($run['exitCode'] !== 0) {
            return $this->fail($log . "\n[docker] docker run failed (exit {$run['exitCode']})", $image);
        }

        $artifact = $ctx->uniappDir . '/dist/build/' . $platformDir;
        if (!is_dir($artifact)) {
            return $this->fail($log . "\n[docker] artifact dir not found after build: {$artifact}", $image);
        }

        return new MobileBuildDriverResult(
            success: true,
            artifactPath: $artifact,
            log: $log,
            runtime: ['driver' => 'docker', 'image' => $image],
        );
    }

    /**
     * @return list<string>
     */
    private function buildCommand(MobileBuildContext $ctx, string $image, string $platformDir): array
    {
        $command = [
            'docker', 'run', '--rm',
            '-v', $ctx->workspaceDir . ':/workspace',
            '-w', '/workspace/uniapp',
        ];

        $memory = MobileBuildEnv::get('MOBILE_BUILD_DOCKER_MEMORY', '') ?? '';
        if ($memory !== '') {
            $command[] = '--memory';
            $command[] = $memory;
        }
        $cpus = MobileBuildEnv::get('MOBILE_BUILD_DOCKER_CPUS', '') ?? '';
        if ($cpus !== '') {
            $command[] = '--cpus';
            $command[] = $cpus;
        }
        $network = MobileBuildEnv::get('MOBILE_BUILD_DOCKER_NETWORK', '') ?? '';
        if ($network !== '') {
            $command[] = '--network';
            $command[] = $network;
        }

        $command[] = $image;
        $command[] = 'sh';
        $command[] = '-lc';
        $command[] = $this->shellScript($platformDir);

        return $command;
    }

    private function shellScript(string $platformDir): string
    {
        $build = $platformDir === 'h5'
            ? 'CI=true pnpm exec uni build'
            : 'CI=true pnpm exec uni build -p ' . $platformDir;

        return 'CI=true pnpm install --prefer-offline --no-frozen-lockfile && ' . $build;
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

    private function fail(string $log, ?string $image): MobileBuildDriverResult
    {
        $runtime = ['driver' => 'docker'];
        if ($image !== null) {
            $runtime['image'] = $image;
        }
        return new MobileBuildDriverResult(success: false, artifactPath: '', log: $log, runtime: $runtime);
    }

    /**
     * 实跑 docker（测试通过子类覆盖此方法注入 fake）。
     *
     * @param list<string> $command
     * @return array{exitCode: int, output: string}
     */
    protected function runDocker(array $command, string $cwd, int $timeoutSec): array
    {
        $process = new Process($command, $cwd, ['CI' => 'true'], null, $timeoutSec);
        $process->run();

        return [
            'exitCode' => $process->getExitCode() ?? 1,
            'output'   => $process->getOutput() . $process->getErrorOutput(),
        ];
    }
}
