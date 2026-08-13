#!/usr/bin/env node
/**
 * 同步插件前端文件到主前端 src 树（symlink）。
 *
 * 用法：
 *   node scripts/sync-plugins.mjs --target tenant
 *   node scripts/sync-plugins.mjs --target platform --clean
 *
 * 行为：
 *   - 扫描 server/plugins/<code>/<target>/<module>/ （module ∈ views|api|locales|components）
 *   - 对每个存在的源目录，在 <target>/src/<module>/plugins/<code>/ 建 symlink
 *   - 记录到 <target>/src/.plugin-sync-manifest.json
 *   - --clean: 反向删除清单中所有 symlink + 空目录 + manifest
 */
import { existsSync, mkdirSync, readdirSync, rmSync, statSync, symlinkSync, writeFileSync, readFileSync, unlinkSync, lstatSync } from 'node:fs'
import { dirname, resolve, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const repoRoot = resolve(__dirname, '..')

const MODULES = ['views', 'api', 'locales', 'components']
const MANIFEST_NAME = '.plugin-sync-manifest.json'

function parseArgs(argv) {
    const args = { target: null, clean: false }
    for (let i = 2; i < argv.length; i++) {
        if (argv[i] === '--target') { args.target = argv[++i] }
        else if (argv[i] === '--clean') { args.clean = true }
    }
    if (!['tenant', 'platform'].includes(args.target)) {
        throw new Error('usage: --target tenant|platform [--clean]')
    }
    return args
}

function readManifest(srcRoot) {
    const p = resolve(srcRoot, MANIFEST_NAME)
    if (!existsSync(p)) return { links: [] }
    return JSON.parse(readFileSync(p, 'utf8'))
}

function writeManifest(srcRoot, data) {
    writeFileSync(resolve(srcRoot, MANIFEST_NAME), JSON.stringify(data, null, 2))
}

function tryUnlink(p) {
    try {
        const stat = lstatSync(p)
        if (stat.isSymbolicLink() || stat.isFile()) unlinkSync(p)
        else rmSync(p, { recursive: true, force: true })
    } catch {}
}

function cleanup(srcRoot) {
    const manifest = readManifest(srcRoot)
    for (const link of manifest.links) {
        tryUnlink(resolve(srcRoot, link))
    }
    for (const mod of MODULES) {
        const dir = resolve(srcRoot, mod, 'plugins')
        if (!existsSync(dir)) continue
        try {
            for (const child of readdirSync(dir)) {
                const childPath = resolve(dir, child)
                if (existsSync(childPath)) {
                    const items = readdirSync(childPath, { withFileTypes: true })
                    if (items.length === 0) rmSync(childPath, { recursive: true })
                }
            }
            if (readdirSync(dir).length === 0) rmSync(dir, { recursive: true })
        } catch {}
    }
    if (existsSync(resolve(srcRoot, MANIFEST_NAME))) unlinkSync(resolve(srcRoot, MANIFEST_NAME))
}

function sync(target) {
    const pluginsRoot = resolve(repoRoot, 'server/plugins')
    const srcRoot = resolve(repoRoot, target, 'src')
    if (!existsSync(srcRoot)) {
        console.error(`[sync] ${srcRoot} 不存在，跳过`)
        return
    }
    cleanup(srcRoot)
    if (!existsSync(pluginsRoot)) {
        console.log(`[sync] ${pluginsRoot} 不存在，无插件可同步`)
        return
    }

    const created = []
    for (const code of readdirSync(pluginsRoot)) {
        const pluginDir = resolve(pluginsRoot, code)
        if (!statSync(pluginDir).isDirectory() || code.startsWith('.')) continue
        const targetDir = resolve(pluginDir, target)
        if (!existsSync(targetDir)) continue

        for (const mod of MODULES) {
            const sourceDir = resolve(targetDir, mod)
            if (!existsSync(sourceDir)) continue
            const linkParent = resolve(srcRoot, mod, 'plugins')
            mkdirSync(linkParent, { recursive: true })
            const linkPath = resolve(linkParent, code)
            const relativeTarget = relative(linkParent, sourceDir)
            symlinkSync(relativeTarget, linkPath, 'dir')
            created.push(relative(srcRoot, linkPath))
            console.log(`[sync] ${target}: ${relativeTarget} -> src/${mod}/plugins/${code}`)
        }
    }
    writeManifest(srcRoot, { links: created, syncedAt: new Date().toISOString() })
    console.log(`[sync] ${target}: ${created.length} symlink(s) 已建立`)
}

const args = parseArgs(process.argv)
const srcRoot = resolve(repoRoot, args.target, 'src')
if (args.clean) {
    cleanup(srcRoot)
    console.log(`[sync] ${args.target}: cleaned`)
} else {
    sync(args.target)
}
