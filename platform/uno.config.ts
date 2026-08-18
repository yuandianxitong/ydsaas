// uno.config.ts
import {
    defineConfig,
    presetAttributify,
    presetIcons,
    presetTypography,
    presetWebFonts,
    transformerDirectives,
    transformerVariantGroup,
} from "unocss";
import presetWind3 from '@unocss/preset-wind3'
import { FileSystemIconLoader } from "@iconify/utils/lib/loader/node-loaders";
import fs from "fs";

// 本地SVG图标目录
const iconsDir = "./src/assets/icons";

// 读取本地 SVG 目录，自动生成 safelist
const generateSafeList = () => {
    try {
        return fs
            .readdirSync(iconsDir)
            .filter((file) => file.endsWith(".svg"))
            .map((file) => `i-svg:${file.replace(".svg", "")}`);
    } catch (error) {
        console.error("无法读取图标目录:", error);
        return [];
    }
};

const staticSafeList = [
  'text-primary','bg-primary','border-primary','bg-sidebar','bg-sidebar-active',
  'text-success','bg-success','border-success',
  'text-warning','bg-warning','border-warning',
  'text-danger','bg-danger','border-danger',
  'text-info','bg-info','border-info',
]

export default defineConfig({
    // 只在 content.pipeline.include 中声明文件扫描路径，切勿再保留顶层 include
    content: {
        pipeline: {
            include: [
                './index.html',
                './src/**/*.{vue,js,ts,jsx,tsx}',
            ],
        },
    },

    presets: [
        presetWind3(),
        presetAttributify(),
        presetIcons({
            // 额外属性
            extraProperties: {
                display: "inline-block",
                width: "1em",
                height: "1em",
            },
            // 图表集合
            collections: {
                // svg 是图标集合名称，使用 `i-svg:图标名` 调用
                svg: FileSystemIconLoader(iconsDir, (svg) => {
                    // 如果 `fill` 没有定义，则添加 `fill="currentColor"`
                    return svg.includes('fill="') ? svg : svg.replace(/^<svg /, '<svg fill="currentColor" ');
                }),
            },
        }),
        presetTypography(),
        presetWebFonts({
            fonts: {
                // ...
            },
        }),
    ],

    theme: {
        colors: {
        primary: 'var(--brand-500)',
        'primary-hover': 'var(--brand-600)',
        'primary-active': 'var(--brand-600)',
        'primary-light': 'var(--brand-400)',
        'primary-bg': 'var(--brand-50)',
        success: 'var(--success)',
        warning: 'var(--amber-500)',
        danger: 'var(--danger)',
        info: 'var(--sky-500)',
        'text-primary': 'var(--color-text-primary)',
        'text-secondary': 'var(--color-text-secondary)',
        'text-tertiary': 'var(--color-text-tertiary)',
        border: 'var(--ink-200)',
        divider: 'var(--ink-100)',
        bg: 'var(--card-bg)',
        surface: 'var(--card-bg)',
        sidebar: 'var(--ink-900)',
        'sidebar-active': 'var(--brand-600)',
        'ink-900': 'var(--ink-900)',
        'ink-800': 'var(--ink-800)',
        'ink-700': 'var(--ink-700)',
        'ink-600': 'var(--ink-600)',
        'ink-500': 'var(--ink-500)',
        'ink-400': 'var(--ink-400)',
        'ink-300': 'var(--ink-300)',
        'ink-200': 'var(--ink-200)',
        'ink-100': 'var(--ink-100)',
        'ink-50': 'var(--ink-50)',
        },
        fontFamily: {
        sans: 'var(--font-sans)',
        },
    },
    shortcuts: [
        ['text-display', 'text-30px font-700 leading-tight'],
        ['text-title', 'text-18px font-600 tracking-wide'],
        ['text-subtitle', 'text-15px font-600'],
        ['text-body', 'text-13px font-400'],
        ['text-caption', 'text-12px font-400'],
        ['btn', 'px-4 py-2 inline-flex items-center justify-center rounded-[var(--r-md)] transition-all duration-200'],
        ['btn-primary', 'btn bg-primary text-white hover:opacity-95 active:opacity-85'],
        ['card', 'bg-white rounded-6px shadow-sm border border-ink-100'],
        ['bg-body', 'bg-[var(--page-bg)]'],
        ['disabled', 'opacity-50 pointer-events-none'],
        ['divider', 'h-[1px] bg-ink-100'],
        ['flex-center', 'flex items-center justify-center'],
    ],
    safelist: [
        ...staticSafeList,
        ...generateSafeList(),
    ],
    transformers: [transformerDirectives(), transformerVariantGroup()],
})
