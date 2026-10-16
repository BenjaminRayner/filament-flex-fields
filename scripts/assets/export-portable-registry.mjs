import fs from 'node:fs'
import path from 'node:path'
import { spawnSync } from 'node:child_process'

export function exportPortableRegistry(packageRoot) {
    const result = spawnSync('php', [path.join(packageRoot, 'scripts/export-asset-registry.php')], {
        cwd: packageRoot,
        encoding: 'utf8',
        maxBuffer: 16 * 1024 * 1024,
    })
    if (result.status !== 0) throw new Error(result.stderr || 'Portable asset registry export failed.')
    const registry = JSON.parse(result.stdout)
    if (registry.format !== 4) throw new Error('Unsupported portable registry format.')
    for (const resource of Object.values(registry.resources)) {
        if (!['distribution', 'package-release', 'registered'].includes(resource.origin)) {
            throw new Error('Unsupported portable resource origin.')
        }
        const file = resource.path
        if (file !== undefined && (typeof file !== 'string' || file.startsWith('/') || file.includes('://')
            || file.includes('\\') || file.split('/').some(part => part === '..' || part === '.'))) {
            throw new Error(`Registry contains a non-portable path: ${file}`)
        }
    }
    for (const bundle of Object.values(registry.bundles)) {
        for (const reference of [...bundle.stylesheets, ...bundle.chunks, ...bundle.ssrScripts, ...(bundle.entry ? [bundle.entry] : [])]) {
            if (!Object.hasOwn(registry.resources, reference)) {
                throw new Error(`Registry references a missing resource: ${reference}`)
            }
        }
    }
    const target = path.join(packageRoot, 'resources/dist/asset-registry.json')
    const temporary = `${target}.${process.pid}.tmp`
    try {
        fs.writeFileSync(temporary, `${JSON.stringify(registry, null, 2)}\n`)
        fs.renameSync(temporary, target)
    } finally {
        if (fs.existsSync(temporary)) fs.unlinkSync(temporary)
    }
}
