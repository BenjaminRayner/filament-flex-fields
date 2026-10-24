import esbuild from 'esbuild'
import { firstPaintEsbuildPlugin, firstPaintVirtualInputs, compileFirstPaintOwners } from './assets/first-paint-esbuild-plugin.mjs'
import fs from 'node:fs'
import path from 'node:path'
import { collectJsMetrics, writeBundleMetrics } from './bundle-metrics.mjs'
import { exportPortableRegistry } from './assets/export-portable-registry.mjs'
import { SEMANTIC_CHUNK_RULES } from './assets/semantic-chunks.mjs'
import { createComponentManifest } from './assets/component-manifest.mjs'
import { analyseModuleBuilds, captureModuleBuild } from './assets/module-build-analysis.mjs'
import { pruneComponentOutput } from './assets/prune-component-output.mjs'
import { withComponentOutputLock } from './assets/component-output-lock.mjs'

const packageRoot = path.resolve(import.meta.dirname, '..')
await withComponentOutputLock(packageRoot, async lease => {
    const jsRoot = path.join(packageRoot, 'resources/js')
    const componentsRoot = path.join(jsRoot, 'components')
    const distRoot = path.join(packageRoot, 'resources/dist/components')
    const entryPoints = Object.fromEntries(fs.readdirSync(componentsRoot).filter(file => file.endsWith('.js')).sort()
        .map(file => [file.slice(0, -3), path.join(componentsRoot, file)]))
    entryPoints['flex-core-runtime'] = path.join(jsRoot, 'entries/flex-core-runtime.js')
    entryPoints['flex-runtime-sdk'] = path.join(jsRoot, 'entries/flex-runtime-sdk.js')
    const entries = Object.keys(entryPoints).sort()
    const owners = await compileFirstPaintOwners(packageRoot)
    const virtualInputs = new Map()
    const sharedOptions = {
        plugins: [firstPaintEsbuildPlugin(packageRoot, virtualInputs, owners)],
        absWorkingDir: packageRoot,
        outdir: distRoot,
        bundle: true,
        splitting: true,
        format: 'esm',
        minify: true,
        entryNames: '[name]',
        chunkNames: 'chunk-[hash]',
        metafile: true,
        write: false,
        loader: { '.svg': 'text' },
    }

    // Entry ownership and dynamic imports provide isolation. Separate compilations
    // would duplicate shared lifecycle code even when the browser shares its state.
    const result = await esbuild.build({ ...sharedOptions, entryPoints })
    for (const owner of owners) {
        Object.assign(result.metafile.inputs, owner.metafile.inputs)
        Object.assign(result.metafile.outputs, owner.metafile.outputs)
        result.outputFiles.push(...owner.outputFiles)
    }
    // The separately compiled synchronous owners belong to this one verified graph.
    // Rewrite external facade edges to owned graph edges before closure projection.
    for (const output of Object.values(result.metafile.outputs)) {
        for (const edge of output.imports) {
            // Facades import their synchronous owner from the same directory.
            // Normalize only its graph key; do not encode the source output
            // directory in emitted browser URLs and shorten attribution later.
            if (edge.external && /^\.\/chunk-first-paint-(segment|timezone|readiness)-[a-f0-9]{64}\.js$/.test(edge.path)) {
                edge.path = `resources/dist/components/${edge.path.slice(2)}`
            }
            if (!Object.hasOwn(result.metafile.outputs, edge.path)) continue
            edge.external = false
        }
    }
    const metafile = result.metafile
    const outputs = new Map(result.outputFiles.map((file) => [path.basename(file.path), file.text]))
    const chunkModules = {}
    const renameMap = {}

    for (const [outputPath, output] of Object.entries(metafile.outputs)) {
        const name = path.basename(outputPath)
        if (!name.startsWith('chunk-')) continue
        const inputs = Object.keys(output.inputs)
        const modules = inputs.map((input) => virtualInputs.get(input)?.identity ?? path.relative(jsRoot, path.resolve(packageRoot, input)))
            .filter((relative) => !relative.startsWith('..')).sort()
        chunkModules[name] = { modules, inputs }
        const firstPaint = name.match(/^chunk-first-paint-(segment|timezone|readiness)-([a-f0-9]{64})\.js$/)
        if (firstPaint) {
            renameMap[name] = `flex-fields-first-paint-${firstPaint[1]}-${firstPaint[2]}.js`
            continue
        }
        const rule = SEMANTIC_CHUNK_RULES.find((candidate) => candidate.matches(modules, inputs))
        const fallback = `flex-fields-${(modules[0] ?? 'shared').replace(/^core\//, '').replace(/^support\//, '').replace(/\.js$/, '').replace(/[^a-z0-9]+/gi, '-')}`
        renameMap[name] = `${rule?.slug ?? fallback}-${name.slice('chunk-'.length)}`
    }

    const manifest = createComponentManifest(metafile, renameMap, entries, chunkModules)
    const stagedOutputs = new Map()
    for (const [name, source] of outputs) {
        let rewritten = source
        for (const [before, after] of Object.entries(renameMap)) {
            rewritten = rewritten.replaceAll(`./${before}`, `./${after}`).replaceAll(`resources/dist/components/${before}`, `./${after}`)
        }
        stagedOutputs.set(renameMap[name] ?? name, rewritten)
    }

    // Complete compilation before touching usable output. Publish chunks before
    // entrypoints and the manifest last. Published hosts own their release retention;
    // the Composer distribution contains only the newly verified build graph.
    fs.mkdirSync(distRoot, { recursive: true })
    const writeOutput = (name, contents) => {
        const target = path.join(distRoot, name)
        const temporary = `${target}.${process.pid}.tmp`
        try {
            fs.writeFileSync(temporary, contents)
            fs.renameSync(temporary, target)
        } finally {
            if (fs.existsSync(temporary)) fs.unlinkSync(temporary)
        }
    }
    for (const name of manifest.__shared_chunks__) writeOutput(name, stagedOutputs.get(name))
    for (const entry of manifest.__entries__) writeOutput(`${entry}.js`, stagedOutputs.get(`${entry}.js`))
    writeOutput('alpine-manifest.json', `${JSON.stringify(manifest, null, 2)}\n`)
    const build = captureModuleBuild(packageRoot, 'components', metafile, {
        virtualInputs,
        outputName: original => `resources/dist/components/${renameMap[path.basename(original)] ?? path.basename(original)}`,
    })
    if (analyseModuleBuilds([build]).sameSourceRepeated) throw new Error('Component build copied a shared source module into multiple outputs.')
    const cleanup = pruneComponentOutput(packageRoot, lease, { virtualInputs: await firstPaintVirtualInputs(packageRoot, owners) })
    const retainedSnapshot = path.join(packageRoot, 'build/asset-analysis/legacy-retained.json')
    if (fs.existsSync(retainedSnapshot)) fs.unlinkSync(retainedSnapshot)
    for (const id of ['segment-ssr', 'timezone-ssr']) {
        const retired = path.join(packageRoot, `build/asset-analysis/${id}.json`)
        if (fs.existsSync(retired)) fs.unlinkSync(retired)
    }
    exportPortableRegistry(packageRoot)
    writeBundleMetrics(packageRoot, { js: collectJsMetrics(distRoot) })

    const audioSource = path.join(packageRoot, 'resources/audio/barcode-scan-success.mp3')
    if (fs.existsSync(audioSource)) {
        const audioDist = path.join(packageRoot, 'resources/dist/assets/barcode-scanner-field')
        fs.mkdirSync(audioDist, { recursive: true })
        fs.copyFileSync(audioSource, path.join(audioDist, 'barcode-scan-success.mp3'))
        const retiredAudio = path.join(packageRoot, 'resources/dist/audio/barcode-scan-success.mp3')
        if (fs.existsSync(retiredAudio)) fs.unlinkSync(retiredAudio)
    }
    console.log(`Built ${entries.length} components; ${manifest.__shared_chunks__.length} shared/dynamic chunks; eager and dynamic edges separated.`)
    console.log(`Removed ${cleanup.removed.length} obsolete generated JS outputs; public host releases untouched.`)
})
