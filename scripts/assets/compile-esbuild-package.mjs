import { open, realpath, unlink } from 'node:fs/promises'
import { randomUUID } from 'node:crypto'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import { createEsbuildPackageSpecification } from './esbuild-package-graph.mjs'

const [metafilePath, declarationPath, workingDirectory, outputRoot, manifestPath] = process.argv.slice(2)
if (process.argv.length !== 7) {
    console.error('Usage: node scripts/assets/compile-esbuild-package.mjs <metafile.json> <declaration.json> <working-directory> <artifact-root> <manifest.json>')
    process.exitCode = 1
} else {
    let temporary
    let created = false
    try {
        const readJson = async (file, limit) => {
            const handle = await open(file, 'r')
            try {
                const bytes = Buffer.alloc(limit + 1)
                let offset = 0
                while (offset < bytes.length) {
                    const result = await handle.read(bytes, offset, bytes.length - offset, offset)
                    if (result.bytesRead === 0) break
                    offset += result.bytesRead
                }
                if (offset > limit) throw new Error('Build metadata exceeds its byte budget')
                return JSON.parse(bytes.subarray(0, offset).toString('utf8'))
            } finally { await handle.close() }
        }
        // Keep the same lexical roots used by esbuild (e.g. /tmp vs /private/tmp).
        // PHP verifies canonical containment and symlinks when reading artifacts.
        await realpath(workingDirectory)
        await realpath(outputRoot)
        const specification = createEsbuildPackageSpecification(await readJson(metafilePath, 32 * 1024 * 1024),
            await readJson(declarationPath, 4 * 1024 * 1024), { workingDirectory: path.resolve(workingDirectory), outputRoot: path.resolve(outputRoot) })
        const directory = await realpath(path.dirname(manifestPath))
        const target = path.join(directory, path.basename(manifestPath))
        for (const input of [metafilePath, declarationPath]) {
            if (target === await realpath(input)) throw new Error('Manifest output cannot replace build metadata')
        }
        temporary = path.join(directory, `.fff-esbuild-${process.pid}-${randomUUID()}.json`)
        const handle = await open(temporary, 'wx', 0o600)
        created = true
        try { await handle.writeFile(`${JSON.stringify(specification)}\n`) } finally { await handle.close() }
        const compiler = path.join(import.meta.dirname, 'compile-package-manifest.php')
        const result = spawnSync(process.env.PHP_BINARY || 'php', [compiler, temporary, outputRoot, manifestPath], { encoding: 'utf8', maxBuffer: 1024 * 1024 })
        if (result.status !== 0) throw new Error(result.stderr || result.error?.message || 'Package manifest compilation failed')
        process.stdout.write(result.stdout)
    } catch (error) {
        console.error(error.message)
        process.exitCode = 1
    } finally {
        if (created) await unlink(temporary)
    }
}
