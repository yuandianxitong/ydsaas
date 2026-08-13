<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use think\Response;
use OpenApi\Attributes as OA;

class ApiDocController extends Controller
{
    /**
     * Swagger UI 页面
     */
    public function index(): Response
    {
        $type = (string) $this->request->param('type', 'admin');
        $openapiUrl = '/platformapi/system/api-doc/openapi.json?type=' . $type;

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>YdAdmin SaaS API 文档</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 20px 0; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: '{$openapiUrl}',
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: "BaseLayout",
            defaultModelsExpandDepth: -1,
            docExpansion: "list",
            filter: true,
            persistAuthorization: true,
        });
    </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 生成 OpenAPI JSON
     */
    #[OA\Get(
        path: '/system/api-doc/openapi.json',
        summary: '获取OpenAPI文档JSON',
        security: [['bearerAuth' => []]],
        tags: ['系统工具'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', description: '文档类型：admin=租户后台API，api=前端应用API，platform=平台API', schema: new OA\Schema(type: 'string', enum: ['admin', 'api', 'platform'], default: 'platform')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OpenAPI JSON文档')
        ]
    )]
    public function openapi(): Response
    {
        $type = (string) $this->request->param('type', 'platform');
        if (!in_array($type, ['platform', 'admin', 'api'], true)) {
            $type = 'platform';
        }

        // 路由驱动生成：注解富文档 + 全量路由补齐（见 RouteApiDocBuilder）
        $data = (new \core\apidoc\RouteApiDocBuilder())->build($type);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
