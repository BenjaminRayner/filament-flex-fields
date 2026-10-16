import assert from 'node:assert/strict'
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import esbuild from 'esbuild'
import { createEsbuildPackageSpecification } from '../assets/esbuild-package-graph.mjs'

const root = path.resolve(process.argv[2] || '')
assert.ok(process.argv.length === 3 && path.basename(root) === 'esbuild-fixture', 'An explicit isolated esbuild-fixture directory is required')
await mkdir(path.join(root, 'src'), { recursive: false })
const contents = {
    'main.js': 'import "./control.css"; import { shared } from "./shared.js"; export default () => ({ init() {} }); export async function run() { const lazy = await import("./lazy.js"); const worker = new Worker(new URL("./worker.js", import.meta.url), { type: "module" }); try { const result = await new Promise((resolve, reject) => { worker.onmessage = event => resolve(event.data); worker.onerror = reject; worker.postMessage("run"); }); return { shared: shared(), lazy: lazy.value(), worker: result }; } finally { worker.terminate(); } }',
    'shared.js': 'export const shared = () => "built-fixture";',
    'lazy.js': 'import { shared } from "./shared.js"; export const value = () => shared() + "-lazy";',
    'worker.js': 'import wasmUrl from "./empty.wasm"; import { shared } from "./shared.js"; self.onmessage = async () => { const bytes = await (await fetch(new URL(wasmUrl, import.meta.url))).arrayBuffer(); await WebAssembly.compile(bytes); self.postMessage({ owner: shared(), wasm: true }); };',
    'helper.js': 'globalThis.builtFixtureSsr = true;',
    'empty.wasm': Buffer.from([0, 97, 115, 109, 1, 0, 0, 0]),
    'control.css': '.esbuild-fixture { color: rgb(0, 128, 0); }',
}
for (const [file, content] of Object.entries(contents)) await writeFile(path.join(root, 'src', file), content, { flag: 'wx' })
const outdir = path.join(root, 'dist')
const result = await esbuild.build({ absWorkingDir: root, entryPoints: { main: 'src/main.js', worker: 'src/worker.js', helper: 'src/helper.js' },
    outdir, bundle: true, splitting: true, format: 'esm', minify: true, metafile: true,
    chunkNames: 'chunks/[name]-[hash]', assetNames: 'assets/[name]-[hash]', loader: { '.wasm': 'file' }, logLevel: 'silent' })
const declaration = { format: 1, package: 'example/esbuild-component',
    entries: { 'built-field': 'main.js' }, styles: { 'built-style': 'main.css' }, helpers: { 'built-ssr': 'helper.js' },
    types: { 'worker.js': 'worker', 'helper.js': 'script' }, resources: { 'main.js': ['worker.js'] } }
const options = { workingDirectory: root, outputRoot: outdir }
const specification = createEsbuildPackageSpecification(result.metafile, declaration, options)
const main = specification.artifacts[specification.entries['built-field']]
assert.ok(main.imports.length > 0 && main.dynamicImports.length > 0 && main.resources.length >= 2, 'Static, lazy or CSS/worker edges were lost')
const worker = Object.values(specification.artifacts).find(artifact => artifact.type === 'worker')
assert.ok(worker.imports.length > 0 && worker.resources.some(id => specification.artifacts[id].type === 'wasm'), 'Worker lost shared module/binary edges')
const reordered = { ...result.metafile, outputs: Object.fromEntries(Object.entries(result.metafile.outputs).reverse()) }
assert.deepEqual(createEsbuildPackageSpecification(reordered, declaration, options), specification, 'Metafile order changed the portable graph')
const altered = () => structuredClone(result.metafile)
const mainOutput = Object.keys(result.metafile.outputs).find(output => path.basename(output) === 'main.js')
assert.ok(mainOutput, 'Built main output missing')
for (const dependency of [{ path: 'missing.js', kind: 'import-statement' }, { path: 'remote-sdk', kind: 'import-statement', external: true },
    { path: Object.keys(result.metafile.outputs)[0], kind: 'unsupported-kind' }]) {
    const metadata = altered()
    metadata.outputs[mainOutput].imports.push(dependency)
    assert.throws(() => createEsbuildPackageSpecification(metadata, declaration, options))
}
const escaped = altered()
escaped.outputs['../private.js'] = escaped.outputs[mainOutput]
assert.throws(() => createEsbuildPackageSpecification(escaped, declaration, options))
assert.throws(() => createEsbuildPackageSpecification(result.metafile, { ...declaration, resources: { 'main.js': ['missing.js'] } }, options))
assert.throws(() => createEsbuildPackageSpecification(result.metafile, { ...declaration, unexpected: true }, options))
const metafile = path.join(root, 'metafile.json')
const mappings = path.join(root, 'declaration.json')
const manifest = path.join(root, 'manifest.json')
await writeFile(metafile, JSON.stringify(result.metafile), { flag: 'wx' })
await writeFile(mappings, JSON.stringify(declaration), { flag: 'wx' })
const compile = output => spawnSync(process.execPath, [path.join(import.meta.dirname, '../assets/compile-esbuild-package.mjs'),
    metafile, mappings, root, outdir, output], { encoding: 'utf8', maxBuffer: 1024 * 1024 })
const compiled = compile(manifest)
assert.equal(compiled.status, 0, compiled.stderr)
const envelope = JSON.parse(compiled.stdout)
const payload = JSON.parse(await readFile(manifest, 'utf8'))
assert.equal(envelope.release, payload.release)
assert.equal(Object.keys(payload.artifacts).length, Object.keys(result.metafile.outputs).length)
assert.ok(!JSON.stringify(payload).includes(root), 'Build metadata leaked absolute paths')
assert.notEqual(compile(metafile).status, 0, 'CLI overwrote its metafile')
assert.deepEqual(JSON.parse(await readFile(metafile, 'utf8')), result.metafile)
assert.equal((await readdir(root)).filter(name => name.startsWith('.fff-')).length, 0, 'CLI retained temporary specification')
process.stdout.write(JSON.stringify({ manifest, artifacts: envelope.artifacts, release: envelope.release, assertions: 'real esbuild static/lazy/CSS/worker/WASM graph, metadata order, rejected missing/external/unsafe edges and CLI preflight' }) + '\n')
