<?php
declare(strict_types=1);

namespace core\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Lang;

/**
 * LocaleMiddleware - 从 Accept-Language header 设置后端语言环境
 *
 * 支持的语言：zh-cn（默认）、en
 * 匹配逻辑：解析 Accept-Language header 的首选语言，映射到 ThinkPHP lang set
 */
class LocaleMiddleware
{
    private const LANG_MAP = [
        'zh'    => 'zh-cn',
        'zh-cn' => 'zh-cn',
        'zh-tw' => 'zh-cn',
        'en'    => 'en',
        'en-us' => 'en',
        'en-gb' => 'en',
    ];

    private const DEFAULT_LANG = 'zh-cn';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->parseAcceptLanguage(
            $request->header('Accept-Language', '')
        );
        Lang::setLangSet($locale);

        return $next($request);
    }

    private function parseAcceptLanguage(string $header): string
    {
        if ($header === '') {
            return self::DEFAULT_LANG;
        }

        // Parse Accept-Language: zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7
        $parts = explode(',', $header);
        $candidates = [];

        foreach ($parts as $part) {
            $segments = explode(';', trim($part));
            $lang = strtolower(trim($segments[0]));
            $quality = 1.0;

            if (isset($segments[1])) {
                $qPart = trim($segments[1]);
                if (str_starts_with($qPart, 'q=')) {
                    $quality = (float) substr($qPart, 2);
                }
            }

            $candidates[] = ['lang' => $lang, 'q' => $quality];
        }

        // Sort by quality descending
        usort($candidates, fn($a, $b) => $b['q'] <=> $a['q']);

        // Find first match
        foreach ($candidates as $candidate) {
            if (isset(self::LANG_MAP[$candidate['lang']])) {
                return self::LANG_MAP[$candidate['lang']];
            }
            // Try base language (e.g., 'zh' from 'zh-hans-cn')
            $base = explode('-', $candidate['lang'])[0];
            if (isset(self::LANG_MAP[$base])) {
                return self::LANG_MAP[$base];
            }
        }

        return self::DEFAULT_LANG;
    }
}
