import fs from 'node:fs'
import { build } from 'esbuild'
import path from 'node:path'
import { createHash } from 'node:crypto'

const contracts = {
    segment: {
        source: 'resources/js/core/segment-overflow-position.js',
        entry: 'resources/js/core/segment-overflow-ssr.js',
        exports: ['preservePositionedSegmentOverflow', 'preserveSegmentPresentation', 'bootSegmentOverflowElements',
            'positionSegmentOverflowScrollElement', 'scrollSegmentItemIntoView', 'updateHorizontalSegmentScrollShadow'],
        constants: ['SEGMENT_OVERFLOW_ATTR', 'SEGMENT_OVERFLOW_SELECTOR',
            'SEGMENT_SELECTED_SELECTOR', 'SEGMENT_PREPARING_CLASS'],
    },
    timezone: {
        source: 'resources/js/core/timezone-browser-ssr-boot.js',
        entry: 'resources/js/core/timezone-browser-ssr-entry.js',
        exports: ['bootTimezoneBrowserSsrElement', 'bootTimezoneBrowserSsrDefaults'],
        constants: [],
    },
}

/** One synchronous implementation; native ESM calls the exact same immutable API. */
export function firstPaintContract(root, name) {
    const contract = contracts[name]
    if (!contract) throw new Error('Unknown first-paint contract.')
    const provenance = [contract.source, contract.entry, 'scripts/assets/first-paint-esbuild-plugin.mjs', 'node_modules/esbuild/package.json']
    const version = createHash('sha256').update(JSON.stringify(provenance.map(file =>
        [file, fs.readFileSync(path.join(root, file), 'utf8')]))).digest('hex')
    return { ...contract, name, version, key: `fff.first-paint.${name}.v1`, provenance }
}

export async function describeFirstPaintInput(root, name, owner = null) {
    owner ??= await compileFirstPaintOwner(root, name)
    const contract = owner.contract
    const { version, key, constants, exports } = contract
    let contents = `if (!globalThis[Symbol.for(${JSON.stringify(key)})]) await import("./${owner.filename}");
function api() {
        const value = globalThis[Symbol.for(${JSON.stringify(key)})];
        if (!value || value.version !== ${JSON.stringify(version)}) throw new Error('FLEX first-paint ${name} unavailable or incompatible; reload required.');
        return value;
    }\n`
    for (const name of constants) contents += `export const ${name} = api().${name};\n`
    for (const name of exports) contents += `export function ${name}(...args) { return api().${name}(...args); }\n`
    return { identity: `virtual/first-paint-v1/${name}.js`, provider: 'first-paint-v1', contents,
        provenance: contract.provenance }
}

export async function firstPaintVirtualInputs(root, owners = []) {
    return new Map(await Promise.all(Object.keys(contracts).map(async name => {
        const descriptor = await describeFirstPaintInput(root, name, owners.find(owner => owner.contract.name === name))
        return [descriptor.identity, descriptor]
    })))
}

export function firstPaintEsbuildPlugin(root, virtualInputs, owners) {
    const byPath = new Map(Object.keys(contracts).map(name => [path.join(root, contracts[name].source), name]))
    return {
        name: 'flex-first-paint',
        setup(build) {
            build.onResolve({ filter: /(?:segment-overflow-position|timezone-browser-ssr-boot)\.js$/ }, args => {
                const resolved = path.resolve(args.resolveDir, args.path)
                const name = byPath.get(resolved)
                return name ? { path: name, namespace: 'first-paint' } : null
            })
            build.onResolve({ filter: /^\.\/chunk-first-paint-/, namespace: 'first-paint' }, args => ({
                path: args.path, external: true,
            }))
            build.onLoad({ filter: /.*/, namespace: 'first-paint' }, async args => {
                const descriptor = await describeFirstPaintInput(root, args.path, owners.find(owner => owner.contract.name === args.path))
                virtualInputs.set(`first-paint:${args.path}`, descriptor)
                return { contents: descriptor.contents, loader: 'js' }
            })
        },
    }
}

/** IIFE is valid both as a blocking inline script and a native ESM side effect. */
export async function compileFirstPaintOwners(root) {
    return Promise.all(Object.keys(contracts).map(name => compileFirstPaintOwner(root, name)))
}

async function compileFirstPaintOwner(root, name) {
    const contract = firstPaintContract(root, name)
    const original = `resources/dist/components/chunk-first-paint-${name}.js`
    const result = await build({
        absWorkingDir: root, entryPoints: [contract.entry], outfile: original,
        bundle: true, format: 'iife', platform: 'browser', target: ['es2020'], minify: true,
        write: false, metafile: true,
        define: { __FFF_FIRST_PAINT_VERSION__: JSON.stringify(contract.version) },
        banner: { js: `// fff-first-paint:${name}:${contract.version}` },
    })
    const hash = createHash('sha256').update(result.outputFiles[0].contents).digest('hex')
    const filename = `chunk-first-paint-${name}-${hash}.js`
    const target = `resources/dist/components/${filename}`
    result.metafile.outputs[target] = result.metafile.outputs[original]
    delete result.metafile.outputs[original]
    result.outputFiles[0].path = path.join(root, target)
    return { ...result, contract, filename }
}
