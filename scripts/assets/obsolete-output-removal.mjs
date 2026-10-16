import fs from 'node:fs'
import path from 'node:path'
import { hashBuildBytes, readModuleBuildFile } from './module-build-analysis.mjs'

/** Preflight every candidate, then recheck identity immediately before unlink. */
export function removeObsoleteOutputs(root, candidates, guard) {
    if (candidates.length > 32768 || new Set(candidates).size !== candidates.length) throw new Error('Invalid obsolete output plan.')
    const planned = candidates.sort().map(name => {
        const bytes = readModuleBuildFile(root, name)
        const stat = fs.lstatSync(path.join(root, name))
        return { path: name, bytes: bytes.length, sha256: hashBuildBytes(bytes), device: stat.dev, inode: stat.ino }
    })
    guard()
    for (const artifact of planned) {
        const target = path.join(root, artifact.path), stat = fs.lstatSync(target)
        if (!stat.isFile() || stat.isSymbolicLink() || stat.dev !== artifact.device || stat.ino !== artifact.inode
            || hashBuildBytes(readModuleBuildFile(root, artifact.path)) !== artifact.sha256) throw new Error('Obsolete output changed during cleanup.')
        fs.unlinkSync(target)
    }
    return planned.map(({ path: name, bytes, sha256 }) => ({ path: name, bytes, sha256 }))
}
