/**
 * Merge plugin uniapp page contributions into a base pages.json structure.
 * Pure function: no FS, no env. I/O wrapper lives in rewritePagesJson() below.
 *
 * @param {object} base - parsed pages.base.json
 * @param {Array<{code: string, manifest: object}>} contributions
 * @returns {object} merged pages.json shape
 */
export function mergePagesJson(base, contributions) {
    const merged = structuredClone(base)
    merged.subPackages = Array.isArray(merged.subPackages) ? merged.subPackages : []
    const existingRoots = new Set(merged.subPackages.map((s) => s.root))

    for (const { code, manifest } of contributions) {
        const uniapp = manifest?.uniapp ?? {}
        const root = uniapp.subpackage
        if (!root) continue
        if (!Array.isArray(uniapp.pages) || uniapp.pages.length === 0) {
            console.warn(`[plugin-uniapp] ${code}: uniapp.subpackage 声明了但 pages 为空，跳过`)
            continue
        }
        if (existingRoots.has(root)) {
            throw new Error(
                `[plugin-uniapp] ${code}: subpackage root "${root}" 与 base/已存在插件冲突`
            )
        }
        existingRoots.add(root)
        merged.subPackages.push({
            root,
            pages: uniapp.pages.map((p) => ({
                path: p.path,
                style: { navigationBarTitleText: p.title }
            }))
        })
    }
    return merged
}

// I/O wrapper — called by sync-plugin-uniapp.mjs
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Scan plugins dir and return contributions sorted alphabetically by code.
 * Skips entries where plugin.json fails to parse.
 */
export function scanPluginManifests(pluginsDir) {
    const out = []
    let entries
    try { entries = readdirSync(pluginsDir).sort() } catch { return out }
    for (const code of entries) {
        const dir = join(pluginsDir, code)
        try { if (!statSync(dir).isDirectory()) continue } catch { continue }
        const manifestFile = join(dir, 'plugin.json')
        try {
            const manifest = JSON.parse(readFileSync(manifestFile, 'utf-8'))
            out.push({ code, manifest })
        } catch {
            // Missing or unreadable plugin.json — silently skip.
        }
    }
    return out
}

/**
 * Read pages.base.json + scan plugins + write merged pages.json.
 */
export function rewritePagesJson({ uniappDir, pluginsDir }) {
    const basePath = join(uniappDir, 'src', 'pages.base.json')
    const targetPath = join(uniappDir, 'src', 'pages.json')
    const base = JSON.parse(readFileSync(basePath, 'utf-8'))
    const contributions = scanPluginManifests(pluginsDir)
    const merged = mergePagesJson(base, contributions)
    writeFileSync(targetPath, JSON.stringify(merged, null, 2) + '\n')
}
