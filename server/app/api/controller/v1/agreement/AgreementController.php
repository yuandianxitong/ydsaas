<?php
declare(strict_types=1);

namespace app\api\controller\v1\agreement;

use core\base\Controller;
use app\service\system\SystemConfigService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '协议', description: '用户协议、隐私政策等内容页')]
class AgreementController extends Controller
{
    protected SystemConfigService $systemConfigService;

    /** 允许的协议标识码 → 配置键前缀 */
    private const ALLOWED = ['user_agreement', 'privacy_policy'];

    #[OA\Get(
        path: '/agreement/{code}',
        summary: '根据标识码获取协议内容',
        tags: ['协议'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, description: '协议标识码（user_agreement、privacy_policy）', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function getByCode(string $code): Response
    {
        try {
            if (empty($code) || !in_array($code, self::ALLOWED, true)) {
                return $this->error(lang('business.record_not_found'));
            }
            $row = $this->systemConfigService->getConfigByKey('agreement_' . $code);
            if (!$row || (int) ($row['status'] ?? 0) !== 1 || ($row['config_value'] ?? '') === '') {
                return $this->error(lang('business.record_not_found'));
            }
            return $this->success(lang('messages.get_success'), [
                'code'    => $code,
                'title'   => $row['config_name'] ?? '',
                'content' => $row['config_value'] ?? '',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
