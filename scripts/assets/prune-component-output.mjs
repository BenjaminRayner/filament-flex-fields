import fs from 'node:fs'
import path from 'node:path'
import { atomicWrite, loadModuleBuild, readModuleBuildFile } from './module-build-analysis.mjs'
import { assertComponentOutputLease } from './component-output-lock.mjs'
import { removeObsoleteOutputs } from './obsolete-output-removal.mjs'

const componentPrefix = 'resources/dist/components/'
const leaf = value => typeof value === 'string' && value.length <= 512
    && /^[a-zA-Z0-9_-]+\.js$/.test(value)

/** Clean the owned build directory, never a host's public assets or source files. */
export function pruneComponentOutput(root, lease, { virtualInputs = new Map() } = {}) {
    root = fs.realpathSync(root)
    assertComponentOutputLease(root, lease)
    const build = loadModuleBuild(root, 'components', { virtualInputs })
    const directory = path.join(root, componentPrefix.slice(0, -1))
    if (fs.realpathSync(directory) !== directory) throw new Error('Unsafe component output directory.')
    const active = new Set(Object.keys(build.outputs).map(name => {
        if (!name.startsWith(componentPrefix) || !leaf(name.slice(componentPrefix.length))) {
            throw new Error('Component cleanup requires a complete owned output graph.')
        }
        return name.slice(componentPrefix.length)
    }))
    const manifestPath = path.join(directory, 'alpine-manifest.json')
    const manifestBytes = readModuleBuildFile(root, `${componentPrefix}alpine-manifest.json`)
    if (manifestBytes.length > 8 * 1024 * 1024) throw new Error('Oversized cleanup manifest.')
    const manifest = JSON.parse(manifestBytes)
    if (!Array.isArray(manifest.__entries__) || !Array.isArray(manifest.__shared_chunks__)
        || manifest.__entries__.length + manifest.__shared_chunks__.length > 16384) throw new Error('Invalid cleanup manifest.')
    const declared = [...manifest.__entries__.map(name => `${name}.js`), ...manifest.__shared_chunks__]
    if (declared.some(name => !leaf(name)) || new Set(declared).size !== declared.length
        || JSON.stringify([...active].sort()) !== JSON.stringify(declared.sort())) {
        throw new Error('Cleanup manifest does not match the verified component graph.')
    }
    const entries = fs.readdirSync(directory, { withFileTypes: true })
    if (entries.length > 32768) throw new Error('Oversized component output directory.')
    const candidates = []
    for (const entry of entries) {
        if (entry.isSymbolicLink()) throw new Error('Component cleanup rejects symlink outputs.')
        if (!entry.name.endsWith('.js') || active.has(entry.name)) continue
        if (!entry.isFile() || !leaf(entry.name)) throw new Error('Unsafe obsolete component output.')
        candidates.push(`${componentPrefix}${entry.name}`)
    }
    // No current registration or source build uses this retired monolithic output.
    const legacy = 'resources/dist/filament-flex-fields.js'
    if (fs.readdirSync(path.join(root, 'resources/dist')).includes('filament-flex-fields.js')) candidates.push(legacy)
    if (['segment', 'timezone'].every(name => [...active].some(file => file.startsWith(`flex-fields-first-paint-${name}-`)))) {
        for (const name of ['segment-overflow-ssr.js', 'timezone-browser-ssr-boot.js']) {
            const obsolete = `resources/dist/core/${name}`
            if (fs.existsSync(path.join(root, obsolete))) candidates.push(obsolete)
        }
    }
    const removed = removeObsoleteOutputs(root, candidates, () => {
        if (fs.readFileSync(manifestPath).compare(manifestBytes) !== 0) throw new Error('Cleanup manifest changed during preflight.')
        loadModuleBuild(root, 'components', { virtualInputs })
    })
    const report = { format: 1, active: active.size, removed }
    atomicWrite(path.join(root, 'build/asset-analysis/component-cleanup.json'), report)
    return report
}
