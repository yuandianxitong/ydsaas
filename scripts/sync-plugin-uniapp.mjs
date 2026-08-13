#!/usr/bin/env node
/**
 * Sync plugin uniapp sources into uniapp/src/<subpackage>/ via symlink (or copy on Windows).
 * Then regenerate uniapp/src/pages.json from pages.base.json + scanned plugins.
 *
 * Triggered by:
 *   pnpm predev:*  →  pnpm run predev:h5  →  node scripts/sync-plugin-uniapp.mjs
 *   pnpm prebuild:* (same)
 *   pnpm uniapp:sync (manual)
 *
 * Exit code:
 *   0 = ok
 *   1 = error (e.g. subpackage root conflict, missing source dir, FS failure)
 */

import { existsSync, lstatSync, readdirSync, readlinkSync, rmSync, symlinkSync, mkdirSync, cpSync, writeFileSync } from 'node:fs'
import { dirname, resolve, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { platform } from 'node:process'
import { rewritePagesJson, scanPluginManifests } from './merge-pages-json.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const REPO_ROOT = resolve(__dirname, '..')
const PLUGINS_DIR = join(REPO_ROOT, 'server', 'plugins')
const UNIAPP_DIR = join(REPO_ROOT, 'uniapp')

// 10 framework-built-in modules — never touched by sync, never pruned
const BUILTIN_MODULES = new Set([
    'login', 'user', 'webview', 'feedback', 'message',
    'payment', 'announcement', 'agreement', 'article', 'about'
])

const isWindows = platform === 'win32'

function log(...args) { console.log('[sync-plugin-uniapp]', ...args) }
function warn(...args) { console.warn('[sync-plugin-uniapp]', ...args) }

/** Materialize source → target via symlink (or copy on Windows). */
function materialize(src, dst) {
    // Skip if already correctly linked.
    if (existsSync(dst)) {
        const stat = lstatSync(dst)
        if (stat.isSymbolicLink()) {
            const current = resolve(dirname(dst), readlinkSync(dst))
            if (current === src) return // already correct
            log(`replacing stale symlink ${dst}`)
            rmSync(dst, { force: true })
        } else if (stat.isDirectory()) {
            // Pre-existing real directory at the target — refuse to nuke.
            throw new Error(
                `target "${dst}" exists as a real directory (not a symlink). ` +
                `Either it's a framework-built-in module (subpackage name conflict — change plugin's uniapp.subpackage), ` +
                `or stale copy state from Windows — delete manually after verifying.`
            )
        } else {
            log(`removing unexpected file at ${dst}`)
            rmSync(dst, { force: true })
        }
    }

    mkdirSync(dirname(dst), { recursive: true })
    if (isWindows) {
        cpSync(src, dst, { recursive: true })
        log(`copied (Windows fallback) ${src} → ${dst}`)
    } else {
        symlinkSync(src, dst, 'dir')
        log(`symlinked ${src} → ${dst}`)
    }
}

/** Remove stale symlinks under uniapp/src/modules/ that point to non-existent plugin dirs. */
function pruneStaleLinks(modulesDir, activeTargets) {
    if (!existsSync(modulesDir)) return
    for (const entry of readdirSync(modulesDir)) {
        if (BUILTIN_MODULES.has(entry)) continue
        const fullPath = join(modulesDir, entry)
        const stat = lstatSync(fullPath)
        // Only prune symlinks. Real (Windows-copy) dirs we leave; user must delete manually.
        if (!stat.isSymbolicLink()) continue
        if (activeTargets.has(fullPath)) continue
        log(`pruning stale symlink ${fullPath}`)
        rmSync(fullPath, { force: true })
    }
}

/**
 * DIY 渲染器协议 v1 —— 主包同步支路 + 静态注册表生成。
 *
 * DIY 页面（首页/会员页）在主包，小程序主包不能引用分包资源，所以插件 DIY 渲染器组件
 * 走独立支路同步到主包路径 src/components/diy/plugins/<code>/（与分包同步解耦：插件可以
 * 只提供 DIY 渲染器、不声明 uniapp.subpackage）。注册表用静态 import（不用
 * defineAsyncComponent —— 小程序端动态异步组件有已知渲染失败案例），dev 态全量生成；
 * 生产态由 server/core/mobile/DiyRendererRegistryGenerator 按租户权益重写。
 */
const RENDERER_NAME_RE = /^[A-Za-z][A-Za-z0-9_-]*$/

function syncDiyRenderers(contributions) {
    const diyPluginsDir = join(UNIAPP_DIR, 'src', 'components', 'diy', 'plugins')
    const activeDiyTargets = new Set()
    const entries = []

    for (const { code, manifest } of contributions) {
        const src = join(PLUGINS_DIR, code, 'uniapp', 'components', 'diy')
        if (existsSync(src)) {
            const dst = join(diyPluginsDir, code)
            activeDiyTargets.add(dst)
            materialize(src, dst)
        }
        for (const w of manifest?.diy_widgets || []) {
            const name = w?.renderer?.uniapp
            if (!w?.type || typeof name !== 'string' || !RENDERER_NAME_RE.test(name)) continue
            if (!existsSync(join(src, `${name}.vue`))) {
                warn(`${code}: diy_widgets renderer.uniapp="${name}" declared but ${src}/${name}.vue missing — skipping registry entry`)
                continue
            }
            entries.push({ type: w.type, code, name })
        }
    }

    // prune 指向已删插件的陈旧软链（复用 modules 同款策略：只清 symlink）
    if (existsSync(diyPluginsDir)) {
        for (const entry of readdirSync(diyPluginsDir)) {
            const fullPath = join(diyPluginsDir, entry)
            if (!lstatSync(fullPath).isSymbolicLink()) continue
            if (activeDiyTargets.has(fullPath)) continue
            log(`pruning stale diy renderer symlink ${fullPath}`)
            rmSync(fullPath, { force: true })
        }
    }

    // 两个生成物恒生成（无插件时为空实现），保证 import 侧确定性：
    // 1) generated/diy-plugin-renderers.ts —— type 集合（宿主判断"命中插件渲染器还是核心回退"）
    // 2) components/diy/plugin-renderer-host.generated.vue —— 静态 v-if 分支宿主。
    //    小程序编译器不支持 <component :is>（编译期直接报错，与异步组件无关），
    //    动态分发只能编译期展开成静态分支。
    const typesContent = [
        '// AUTO-GENERATED by scripts/sync-plugin-uniapp.mjs — do not edit.',
        '// 有插件自带 DIY 渲染器的 widget type 集合；渲染分发见 components/diy/plugin-renderer-host.generated.vue。',
        '// 生产构建由 server/core/mobile/DiyRendererRegistryGenerator 按租户权益重写两个生成物。',
        `export const DIY_PLUGIN_RENDERER_TYPES = new Set<string>(${JSON.stringify(entries.map((e) => e.type))})`,
        '',
    ].join('\n')
    mkdirSync(join(UNIAPP_DIR, 'src', 'generated'), { recursive: true })
    writeFileSync(join(UNIAPP_DIR, 'src', 'generated', 'diy-plugin-renderers.ts'), typesContent)

    const hostContent = [
        '<!-- AUTO-GENERATED by scripts/sync-plugin-uniapp.mjs — do not edit. -->',
        '<template>',
        ...(entries.length
            ? entries.map((e, i) => `  <R${i} ${i === 0 ? 'v-if' : 'v-else-if'}="type === '${e.type}'" :props="props" />`)
            : ['  <!-- 无插件 DIY 渲染器 -->']),
        '</template>',
        '',
        '<script setup lang="ts">',
        ...entries.map((e, i) => `import R${i} from '@/components/diy/plugins/${e.code}/${e.name}.vue'`),
        "defineProps<{ type: string; props: Record<string, any> }>()",
        '</script>',
        '',
    ].join('\n')
    writeFileSync(join(UNIAPP_DIR, 'src', 'components', 'diy', 'plugin-renderer-host.generated.vue'), hostContent)
    log(`diy renderer registry generated (${entries.length} entry(ies))`)
}

function main() {
    const contributions = scanPluginManifests(PLUGINS_DIR)
    const activeTargets = new Set()

    for (const { code, manifest } of contributions) {
        const subpackage = manifest?.uniapp?.subpackage
        if (!subpackage) continue

        const src = join(PLUGINS_DIR, code, 'uniapp')
        if (!existsSync(src)) {
            warn(`${code}: declares uniapp.subpackage="${subpackage}" but ${src} does not exist — skipping`)
            continue
        }

        const dst = join(UNIAPP_DIR, 'src', subpackage)
        activeTargets.add(dst)
        materialize(src, dst)
    }

    pruneStaleLinks(join(UNIAPP_DIR, 'src', 'modules'), activeTargets)

    syncDiyRenderers(contributions)

    rewritePagesJson({ uniappDir: UNIAPP_DIR, pluginsDir: PLUGINS_DIR })
    log('pages.json regenerated')
}

try {
    main()
} catch (err) {
    console.error('[sync-plugin-uniapp] ERROR:', err.message)
    process.exit(1)
}
