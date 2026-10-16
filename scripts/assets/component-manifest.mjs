import path from 'node:path'

/** Build dependency semantics from esbuild output edges, never minified JS. */
export function createComponentManifest(metafile, renameMap, entryNames, chunkModules) {
    const eager = {}
    const dynamic = {}
    const nameFor = (output) => renameMap[path.basename(output)] ?? path.basename(output)
    const files = new Set(Object.keys(metafile.outputs).map(nameFor))

    for (const [outputPath, output] of Object.entries(metafile.outputs)) {
        const name = nameFor(outputPath)
        eager[name] = []
        dynamic[name] = []

        for (const dependency of output.imports) {
            if (dependency.external) continue
            const target = nameFor(dependency.path)
            if (!files.has(target)) throw new Error(`Missing output dependency: ${name} -> ${target}`)
            const graph = dependency.kind === 'dynamic-import' ? dynamic : eager
            graph[name].push(target)
        }

        eager[name] = [...new Set(eager[name])].sort()
        dynamic[name] = [...new Set(dynamic[name])].sort()
    }

    const closure = (entry) => {
        const seen = new Set()
        const pending = [...(eager[entry] ?? [])]
        while (pending.length) {
            const next = pending.pop()
            if (seen.has(next) || next === entry) continue
            seen.add(next)
            pending.push(...(eager[next] ?? []))
        }
        return [...seen].sort()
    }

    const manifest = Object.fromEntries(entryNames.map((name) => [name, closure(`${name}.js`)]))
    const entries = new Set(entryNames.map((name) => `${name}.js`))
    manifest.__format__ = 2
    manifest.__entries__ = [...entryNames].sort()
    // Dynamic chunks must be published even though they are not eagerly preloaded.
    manifest.__shared_chunks__ = [...files].filter((name) => !entries.has(name)).sort()
    manifest.__chunk_imports__ = eager
    manifest.__dynamic_imports__ = dynamic
    manifest.__chunk_modules__ = Object.fromEntries(Object.entries(chunkModules).map(([name, chunk]) => [nameFor(name), chunk.modules]))

    return manifest
}
