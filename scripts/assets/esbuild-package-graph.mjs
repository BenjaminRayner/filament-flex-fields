import { createHash } from 'node:crypto'
import path from 'node:path'
import { assertFlexSdkExternalization } from './flex-sdk-plugin.mjs'

const types = new Map(Object.entries({
    js: 'module', mjs: 'module', css: 'stylesheet', wasm: 'wasm',
    woff: 'font', woff2: 'font', ttf: 'font', otf: 'font',
    png: 'image', jpg: 'image', jpeg: 'image', webp: 'image', avif: 'image', gif: 'image', svg: 'image',
    mp3: 'audio', wav: 'audio', ogg: 'audio', m4a: 'audio', aac: 'audio', flac: 'audio',
    mp4: 'video', webm: 'video', mov: 'video', json: 'data', bin: 'binary', onnx: 'binary',
}))
const object = (value, message) => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(message)
    return value
}
const portablePath = value => {
    if (typeof value !== 'string' || value.length > 512
        || !/^[a-zA-Z0-9_-][a-zA-Z0-9_.-]*(?:\/[a-zA-Z0-9_-][a-zA-Z0-9_.-]*)*$/.test(value)
        || value.split('/').some(part => part.endsWith('.') || /^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i.test(part))) {
        throw new Error(`Nonportable output path: ${value}`)
    }
    return value
}
const identity = value => {
    if (typeof value !== 'string' || value.length > 96 || !/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/.test(value)) throw new Error('Invalid manifest identity')
    return value
}

/**
 * Read output edges from esbuild's machine-readable metafile. Opaque new URL()
 * and Worker() edges remain explicit supplemental resources. No source scanning,
 * output rewriting, host URLs or private input paths enter the portable payload.
 */
export function createEsbuildPackageSpecification(metafile, declaration, { workingDirectory, outputRoot }) {
    assertFlexSdkExternalization(object(metafile, 'Invalid esbuild metafile'), { workingDirectory })
    object(declaration, 'Invalid esbuild package declaration')
    const keys = ['format', 'package', 'entries', 'styles', 'helpers', 'types', 'resources']
    if (Object.keys(declaration).length !== keys.length || keys.some(key => !Object.hasOwn(declaration, key))
        || declaration.format !== 1 || typeof declaration.package !== 'string' || declaration.package.length > 192
        || !/^[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?\/[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?$/.test(declaration.package)) {
        throw new Error('Unsupported or incomplete esbuild package declaration')
    }
    portablePath(declaration.package)
    const outputs = object(object(metafile, 'Invalid esbuild metafile').outputs, 'Missing esbuild outputs')
    if (Object.keys(outputs).length === 0 || Object.keys(outputs).length > 4096) throw new Error('Esbuild output budget exceeded')
    const root = path.resolve(outputRoot)
    const nodes = new Map()
    const outputIds = new Map()
    const overrides = object(declaration.types, 'Invalid artifact type overrides')
    for (const [output, metadata] of Object.entries(outputs).sort(([a], [b]) => a.localeCompare(b, 'en'))) {
        object(metadata, 'Invalid esbuild output metadata')
        if (typeof output !== 'string' || output.length > 4096) throw new Error('Invalid esbuild output path')
        const absolute = path.resolve(workingDirectory, output)
        const relative = portablePath(path.relative(root, absolute).split(path.sep).join('/'))
        const inferred = types.get(path.extname(relative).slice(1).toLowerCase())
        const type = overrides[relative] ?? inferred
        if (!type || ![...types.values(), 'worker', 'script'].includes(type)) throw new Error(`Unsupported esbuild output type: ${relative}`)
        if (nodes.has(relative) || outputIds.has(absolute)) throw new Error('Duplicated esbuild output path')
        const id = `output-${createHash('sha256').update(relative).digest('hex')}`
        nodes.set(relative, { id, metadata, artifact: { path: relative, type, imports: [], dynamicImports: [], resources: [] } })
        outputIds.set(absolute, id)
    }
    for (const output of Object.keys(overrides)) if (!nodes.has(portablePath(output))) throw new Error(`Type override refers to missing output: ${output}`)
    const resolveOutput = value => {
        if (typeof value !== 'string' || value.length > 4096) throw new Error('Invalid output dependency path')
        const id = outputIds.get(path.resolve(workingDirectory, value))
        if (!id) throw new Error(`Missing esbuild output dependency: ${value}`)
        return id
    }
    let edges = 0
    for (const { metadata, artifact } of nodes.values()) {
        if (!Array.isArray(metadata.imports) || metadata.imports.length > 1024) throw new Error('Invalid esbuild imports')
        for (const dependency of metadata.imports) {
            object(dependency, 'Invalid esbuild dependency')
            // External imports need a separate SDK/deployment contract. Silently
            // omitting them would advertise a complete graph that cannot run.
            if (dependency.external) throw new Error(`Undeclared external import: ${artifact.path} -> ${dependency.path}`)
            const id = resolveOutput(dependency.path)
            const edge = dependency.kind === 'dynamic-import' ? 'dynamicImports'
                : ['file-loader', 'url-token'].includes(dependency.kind) ? 'resources'
                    : ['import-statement', 'import-rule'].includes(dependency.kind) ? 'imports' : null
            if (!edge) throw new Error(`Unsupported browser output edge: ${dependency.kind}`)
            artifact[edge].push(id)
            edges++
        }
        if (metadata.cssBundle !== undefined) {
            artifact.resources.push(resolveOutput(metadata.cssBundle))
            edges++
        }
    }
    for (const [source, dependencies] of Object.entries(object(declaration.resources, 'Invalid supplemental resource edges'))) {
        const node = nodes.get(portablePath(source))
        if (!node || !Array.isArray(dependencies) || dependencies.length > 1024) throw new Error(`Invalid supplemental resource owner: ${source}`)
        for (const dependency of dependencies) {
            const target = nodes.get(portablePath(dependency))
            if (!target) throw new Error(`Missing supplemental output: ${source} -> ${dependency}`)
            node.artifact.resources.push(target.id)
            edges++
        }
    }
    if (edges > 32768) throw new Error('Esbuild edge budget exceeded')
    const roots = (name, type) => {
        const result = {}
        const map = object(declaration[name], 'Invalid esbuild root map')
        if (Object.keys(map).length > 1024) throw new Error('Esbuild root budget exceeded')
        for (const [logical, output] of Object.entries(map).sort(([a], [b]) => a.localeCompare(b, 'en'))) {
            const node = nodes.get(portablePath(output))
            if (!node || node.artifact.type !== type) throw new Error(`Invalid ${name} root: ${logical} -> ${output}`)
            result[identity(logical)] = node.id
        }
        return result
    }
    for (const { artifact } of nodes.values()) {
        for (const edge of ['imports', 'dynamicImports', 'resources']) artifact[edge] = [...new Set(artifact[edge])].sort()
    }
    return { format: 1, package: declaration.package,
        artifacts: Object.fromEntries([...nodes.values()].map(node => [node.id, node.artifact])),
        entries: roots('entries', 'module'), styles: roots('styles', 'stylesheet'), helpers: roots('helpers', 'script') }
}
