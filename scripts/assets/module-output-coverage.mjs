import fs from 'node:fs'
import path from 'node:path'

/** Every distributed JS output must be attributed or explicitly opaque. */
export function verifyModuleOutputCoverage(root, builds, opaqueBuilds = []) {
    const attributed = builds.flatMap(build => Object.keys(build.outputs)).sort()
    const opaque = opaqueBuilds.flatMap(build => Object.keys(build.files)).sort()
    const identities = [...attributed, ...opaque].sort()
    if (new Set(identities).size !== identities.length) throw new Error('Distributed JavaScript has conflicting accounting owners.')
    let files = 0
    const walk = (directory, depth = 0) => {
        if (depth > 128) throw new Error('Distributed asset inventory exceeds its depth limit.')
        const result = []
        for (const item of fs.readdirSync(directory, { withFileTypes: true })) {
            if (++files > 32768) throw new Error('Oversized distributed asset inventory.')
            const name = path.join(directory, item.name)
            if (item.isSymbolicLink()) throw new Error('Distributed JavaScript inventory cannot contain symlinks.')
            if (item.isDirectory()) result.push(...walk(name, depth + 1))
            else if (/\.(?:m?js)$/.test(item.name)) result.push(path.relative(root, name).split(path.sep).join('/'))
        }
        return result
    }
    if (JSON.stringify(walk(path.join(root, 'resources/dist')).sort()) !== JSON.stringify(identities)) {
        throw new Error('JavaScript duplication inventory has missing or unexpected distributed outputs.')
    }
    return { attributedJavascript: attributed.length, opaqueDistributedJavascript: opaque.length,
        sourceAttributionComplete: opaque.length === 0 }
}
