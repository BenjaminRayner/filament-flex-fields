import fs from 'node:fs'
import path from 'node:path'
import { atomicWrite, hashBuildBytes, readModuleBuildFile } from './module-build-analysis.mjs'

const validId = value => typeof value === 'string' && /^[a-z][a-z0-9-]{0,63}$/.test(value)
const validText = value => typeof value === 'string' && value.length > 0 && value.length <= 1024 && !/[\u0000-\u001f]/.test(value)

/** Byte integrity is not source attribution. Keep prebuilt/legacy costs explicit. */
export function captureOpaqueBuild(root, id, files, { reason, provenance = [] } = {}) {
    if (!validId(id) || !validText(reason) || !Array.isArray(files) || !files.length || files.length > 16384
        || !Array.isArray(provenance) || provenance.length > 32) throw new Error('Invalid opaque build descriptor.')
    const describe = name => {
        const bytes = readModuleBuildFile(root, name)
        return [name, { bytes: bytes.length, sha256: hashBuildBytes(bytes) }]
    }
    const snapshot = { format: 1, id, reason, files: Object.fromEntries([...files].sort().map(describe)),
        provenance: Object.fromEntries([...provenance].sort().map(describe)) }
    if (Object.keys(snapshot.files).length !== files.length || Object.keys(snapshot.provenance).length !== provenance.length) {
        throw new Error('Duplicate opaque build file identity.')
    }
    const directory = path.join(root, 'build/asset-analysis')
    fs.mkdirSync(directory, { recursive: true })
    atomicWrite(path.join(directory, `${id}.json`), snapshot)
    return snapshot
}

export function loadOpaqueBuild(root, id) {
    if (!validId(id)) throw new Error('Invalid opaque build identity.')
    const target = path.join(root, 'build/asset-analysis', `${id}.json`)
    if (fs.statSync(target).size > 8 * 1024 * 1024) throw new Error('Oversized opaque build snapshot.')
    const snapshot = JSON.parse(fs.readFileSync(target, 'utf8'))
    if (snapshot.format !== 1 || snapshot.id !== id || !validText(snapshot.reason)) throw new Error('Invalid opaque build snapshot.')
    for (const [key, maximum, minimum] of [['files', 16384, 1], ['provenance', 32, 0]]) {
        const map = snapshot[key]
        if (!map || typeof map !== 'object' || Array.isArray(map)
            || Object.keys(map).length < minimum || Object.keys(map).length > maximum) throw new Error('Invalid opaque build files.')
        for (const [name, metadata] of Object.entries(map)) {
            const bytes = readModuleBuildFile(root, name)
            if (!metadata || !Number.isSafeInteger(metadata.bytes) || metadata.bytes < 0
                || metadata.bytes !== bytes.length || hashBuildBytes(bytes) !== metadata.sha256) throw new Error(`Stale opaque build file: ${name}`)
        }
    }
    return snapshot
}
