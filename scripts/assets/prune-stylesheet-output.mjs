import fs from 'node:fs'
import path from 'node:path'
import { assertComponentOutputLease } from './component-output-lock.mjs'
import { atomicWrite, hashBuildBytes, readModuleBuildFile } from './module-build-analysis.mjs'
import { removeObsoleteOutputs } from './obsolete-output-removal.mjs'

const leaf = name => typeof name === 'string' && /^[a-zA-Z0-9_-]+\.css$/.test(name)

/** Partial CSS builds must never prune other components' current outputs. */
export function pruneStylesheetOutput(root, completed, lease) {
    root = fs.realpathSync(root)
    assertComponentOutputLease(root, lease)
    const sourceDirectory = path.join(root, 'resources/css/entries')
    const outputDirectory = path.join(root, 'resources/dist/css')
    if (fs.realpathSync(sourceDirectory) !== sourceDirectory || fs.realpathSync(outputDirectory) !== outputDirectory) throw new Error('Unsafe stylesheet directory.')
    const sources = fs.readdirSync(sourceDirectory).filter(name => leaf(name)).sort()
    if (!Array.isArray(completed) || completed.length > 16384 || completed.some(name => !leaf(name))
        || new Set(completed).size !== completed.length || JSON.stringify([...completed].sort()) !== JSON.stringify(sources)) {
        throw new Error('Stylesheet cleanup requires every current entry to have completed.')
    }
    const active = new Set([...sources, 'core.css', 'playground.css'])
    const artifacts = [...active].map(name => {
        const file = `resources/dist/css/${name}`
        return { file, sha256: hashBuildBytes(readModuleBuildFile(root, file)) }
    })
    const entries = fs.readdirSync(outputDirectory, { withFileTypes: true })
    if (entries.length > 32768) throw new Error('Oversized stylesheet directory.')
    const candidates = []
    for (const entry of entries) {
        if (entry.isSymbolicLink()) throw new Error('Stylesheet cleanup rejects symlinks.')
        if (!entry.name.endsWith('.css') || active.has(entry.name)) continue
        if (!entry.isFile() || !leaf(entry.name)) throw new Error('Unsafe obsolete stylesheet.')
        candidates.push(`resources/dist/css/${entry.name}`)
    }
    if (fs.readdirSync(path.join(root, 'resources/dist')).includes('filament-flex-fields.css')) candidates.push('resources/dist/filament-flex-fields.css')
    const removed = removeObsoleteOutputs(root, candidates, () => {
        if (JSON.stringify(fs.readdirSync(sourceDirectory).filter(name => leaf(name)).sort()) !== JSON.stringify(sources)) throw new Error('Stylesheet entries changed during cleanup.')
        for (const artifact of artifacts) {
            if (hashBuildBytes(readModuleBuildFile(root, artifact.file)) !== artifact.sha256) throw new Error('Current stylesheet changed during cleanup.')
        }
    })
    const report = { format: 1, active: active.size, removed }
    atomicWrite(path.join(root, 'build/asset-analysis/stylesheet-cleanup.json'), report)
    return report
}
