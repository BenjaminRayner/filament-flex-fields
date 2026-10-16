import fs from 'node:fs'
import path from 'node:path'
import { createHash } from 'node:crypto'

const digest = bytes => createHash('sha256').update(bytes).digest('hex')
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value)
const safeName = value => typeof value === 'string' && value.length <= 512
    && /^[a-zA-Z0-9_@./+ -]+$/.test(value) && !value.startsWith('/')
    && value.split('/').every(part => part && part !== '.' && part !== '..')
const size = value => Number.isSafeInteger(value) && value >= 0
const sha = value => typeof value === 'string' && /^[a-f0-9]{64}$/.test(value)
const boundedEntries = value => {
    if (!object(value) || Object.keys(value).length > 16384) throw new Error('Invalid or oversized module build metadata.')
    return Object.entries(value).sort(([a], [b]) => a.localeCompare(b, 'en'))
}
const assertId = id => {
    if (typeof id !== 'string' || !/^[a-z][a-z0-9-]{0,63}$/.test(id)) throw new Error('Invalid module build identity.')
}
const virtual = metadata => metadata?.kind === 'virtual'
const validateVirtual = (name, metadata) => {
    assertId(metadata.provider)
    if (!name.startsWith(`virtual/${metadata.provider}/`) || !Array.isArray(metadata.provenance)
        || !metadata.provenance.length || metadata.provenance.length > 32
        || new Set(metadata.provenance).size !== metadata.provenance.length
        || metadata.provenance.some(source => !safeName(source) || source.startsWith('virtual/'))) {
        throw new Error('Invalid virtual module provenance.')
    }
}
const file = (root, name) => {
    if (!safeName(name)) throw new Error('Module build paths must be portable repository-relative names.')
    const target = path.join(root, name)
    const relative = path.relative(fs.realpathSync(root), fs.realpathSync(target))
    if (relative.startsWith('../') || path.isAbsolute(relative) || !fs.statSync(target).isFile()) {
        throw new Error('Module build file escaped its repository root.')
    }
    if (relative.split(path.sep).join('/') !== name) throw new Error('Module build metadata must use canonical file identities, not symlink aliases.')
    return fs.readFileSync(target)
}
export { file as readModuleBuildFile, digest as hashBuildBytes }

/** Build-time only: retain attributed emitted bytes, not source text or host URLs. */
export function captureModuleBuild(root, id, metafile, { outputName = name => name, virtualInputs = new Map() } = {}) {
    assertId(id)
    const aliases = new Map()
    const inputs = Object.fromEntries(boundedEntries(metafile.inputs).map(([name, metadata]) => {
        const supplied = virtualInputs.get(name)
        if (supplied) {
            const bytes = Buffer.from(supplied.contents)
            const descriptor = { kind: 'virtual', provider: supplied.provider,
                bytes: bytes.length, sha256: digest(bytes), provenance: [...supplied.provenance].sort() }
            if (!safeName(supplied.identity) || metadata.bytes !== bytes.length) throw new Error('Invalid virtual input identity or bytes.')
            validateVirtual(supplied.identity, descriptor)
            aliases.set(name, supplied.identity)
            return [supplied.identity, descriptor]
        }
        if (name.startsWith('virtual/')) throw new Error('Virtual inputs require explicit provenance.')
        const bytes = file(root, name)
        if (metadata.bytes !== bytes.length) throw new Error(`Build input changed during analysis: ${name}`)
        return [name, { bytes: bytes.length, sha256: digest(bytes) }]
    }))
    if (Object.keys(inputs).length !== Object.keys(metafile.inputs).length) throw new Error('Build input identities collided.')
    for (const metadata of Object.values(inputs)) {
        if (!virtual(metadata)) continue
        for (const source of metadata.provenance) {
            const bytes = file(root, source), dependency = { bytes: bytes.length, sha256: digest(bytes) }
            if (inputs[source] && (inputs[source].bytes !== dependency.bytes || inputs[source].sha256 !== dependency.sha256)) {
                throw new Error('Virtual provenance changed during analysis.')
            }
            inputs[source] = dependency
        }
    }
    const outputs = Object.fromEntries(boundedEntries(metafile.outputs).map(([original, metadata]) => {
        const name = outputName(original)
        const bytes = file(root, name)
        const contributions = Object.fromEntries(boundedEntries(metadata.inputs).map(([originalSource, contribution]) => {
            const source = aliases.get(originalSource) ?? originalSource
            if (!Object.hasOwn(inputs, source) || !size(contribution.bytesInOutput)) throw new Error('Invalid emitted module contribution.')
            return [source, contribution.bytesInOutput]
        }))
        return [name, { bytes: bytes.length, sha256: digest(bytes), inputs: contributions }]
    }))
    if (Object.keys(outputs).length !== Object.keys(metafile.outputs).length) throw new Error('Build output names collided.')
    const snapshot = { format: 1, id, inputs, outputs }
    const directory = path.join(root, 'build/asset-analysis')
    fs.mkdirSync(directory, { recursive: true })
    analyseModuleBuilds([snapshot])
    atomicWrite(path.join(directory, `${id}.json`), snapshot)
    return snapshot
}

export function atomicWrite(target, value) {
    const temporary = `${target}.${process.pid}.tmp`
    try {
        fs.writeFileSync(temporary, `${JSON.stringify(value, null, 2)}\n`)
        fs.renameSync(temporary, target)
    } finally {
        if (fs.existsSync(temporary)) fs.unlinkSync(temporary)
    }
}

/** Never identify a library by basename or merge its incompatible installed versions. */
export function analyseModuleBuilds(builds, { verifyFile = null, verifyVirtualInput = null } = {}) {
    if (!Array.isArray(builds) || !builds.length || builds.length > 64) throw new Error('Invalid module build set.')
    const identities = new Set(), sources = new Map(), outputOwners = new Map()
    const summaries = []
    let edges = 0
    for (const build of [...builds].sort((a, b) => String(a.id).localeCompare(String(b.id), 'en'))) {
        assertId(build.id)
        if (build.format !== 1 || identities.has(build.id)) throw new Error('Duplicate or unsupported module build.')
        identities.add(build.id)
        if (!object(build.inputs) || !Object.keys(build.inputs).length || !object(build.outputs) || !Object.keys(build.outputs).length) {
            throw new Error('Module build snapshots must contain real inputs and outputs.')
        }
        for (const [name, metadata] of boundedEntries(build.inputs)) {
            if (!safeName(name) || !object(metadata) || !size(metadata.bytes) || !sha(metadata.sha256)) throw new Error('Invalid source metadata.')
            if (virtual(metadata)) {
                validateVirtual(name, metadata)
                if (metadata.provenance.some(source => !Object.hasOwn(build.inputs, source) || virtual(build.inputs[source]))) {
                    throw new Error('Missing physical virtual module provenance.')
                }
                verifyVirtualInput?.(name, metadata)
            } else {
                if (name.startsWith('virtual/') || (metadata.kind !== undefined && metadata.kind !== 'file')) throw new Error('Invalid source kind.')
                verifyFile?.(name, metadata)
            }
            const prior = sources.get(name)
            if (prior && (prior.bytes !== metadata.bytes || prior.sha256 !== metadata.sha256
                || prior.kind !== metadata.kind || prior.provider !== metadata.provider
                || JSON.stringify(prior.provenance) !== JSON.stringify(metadata.provenance))) throw new Error(`Mixed source generations: ${name}`)
            if (!prior) sources.set(name, { ...metadata, emissions: [] })
            if (sources.size > 65536) throw new Error('Oversized combined source catalog.')
        }
        let outputBytes = 0
        for (const [name, metadata] of boundedEntries(build.outputs)) {
            if (!safeName(name) || !object(metadata) || !size(metadata.bytes) || !sha(metadata.sha256) || outputOwners.has(name)) {
                throw new Error('Invalid or multiply-owned build output.')
            }
            verifyFile?.(name, metadata)
            outputOwners.set(name, build.id)
            outputBytes += metadata.bytes
            if (!size(outputBytes)) throw new Error('Combined output size exceeded safe accounting.')
            for (const [source, bytes] of boundedEntries(metadata.inputs)) {
                if (++edges > 262144 || !Object.hasOwn(build.inputs, source) || !size(bytes) || bytes > metadata.bytes) throw new Error(`Invalid or oversized module attribution graph: ${build.id}/${name}, source ${source}, contribution ${bytes}, sealed output ${metadata.bytes}.`)
                if (bytes > 0) sources.get(source).emissions.push({ build: build.id, output: name, bytes })
            }
        }
        summaries.push({ id: build.id, inputs: Object.keys(build.inputs).length, outputs: Object.keys(build.outputs).length, outputBytes })
    }
    const duplicates = [...sources].filter(([, source]) => source.emissions.length > 1)
        .sort(([a], [b]) => a.localeCompare(b, 'en')).map(([input, source]) => ({
            input, sourceBytes: source.bytes, emissions: source.emissions,
            // Attribution estimate, not gzip savings or byte-for-byte identical functions.
            extraAttributedBytes: source.emissions.reduce((sum, item) => sum + item.bytes, 0)
                - Math.max(...source.emissions.map(item => item.bytes)),
        }))
    return {
        format: 1, scope: summaries.map(build => build.id), builds: summaries,
        sameSourceRepeated: duplicates.length,
        ownSourceRepeated: duplicates.filter(item => item.input.startsWith('resources/js/')).length,
        extraAttributedBytes: duplicates.reduce((sum, item) => sum + item.extraAttributedBytes, 0),
        duplicates,
    }
}

/** Refuse stale source/output snapshots, including a same-size/same-mtime edit. */
export function loadModuleBuild(root, id, { virtualInputs = new Map() } = {}) {
    assertId(id)
    const target = path.join(root, 'build/asset-analysis', `${id}.json`)
    if (fs.statSync(target).size > 8 * 1024 * 1024) throw new Error('Oversized module build snapshot.')
    const build = JSON.parse(fs.readFileSync(target, 'utf8'))
    if (build.id !== id) throw new Error('Wrong module build snapshot identity.')
    analyseModuleBuilds([build], { verifyVirtualInput(name, metadata) {
        const descriptor = virtualInputs.get(name)
        if (!descriptor || descriptor.provider !== metadata.provider
            || JSON.stringify([...descriptor.provenance].sort()) !== JSON.stringify(metadata.provenance)) {
            throw new Error('Missing or stale virtual module verifier.')
        }
        const bytes = Buffer.from(descriptor.contents)
        if (bytes.length !== metadata.bytes || digest(bytes) !== metadata.sha256) throw new Error(`Stale virtual module snapshot: ${name}`)
    }, verifyFile(name, metadata) {
        const bytes = file(root, name)
        if (bytes.length !== metadata.bytes || digest(bytes) !== metadata.sha256) throw new Error(`Stale module build snapshot: ${name}`)
    } })
    return build
}
