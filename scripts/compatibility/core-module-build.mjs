import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { build } from 'esbuild'
import { analyseModuleBuilds, captureModuleBuild, loadModuleBuild } from '../assets/module-build-analysis.mjs'
import { createComponentManifest } from '../assets/component-manifest.mjs'
import { captureOpaqueBuild, loadOpaqueBuild } from '../assets/opaque-build-analysis.mjs'
import { verifyModuleOutputCoverage } from '../assets/module-output-coverage.mjs'
import { withComponentOutputLock } from '../assets/component-output-lock.mjs'
import { pruneComponentOutput } from '../assets/prune-component-output.mjs'
import { pruneStylesheetOutput } from '../assets/prune-stylesheet-output.mjs'
import { spawnSync } from 'node:child_process'

// Existing suites do not cover metafile attribution or shared compilation. Keep
// these isolated build proofs outside tests/**; never mutate application data.
const root = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-module-build-'))
const proofs = []
const write = (name, contents) => {
    const target = path.join(root, name)
    fs.mkdirSync(path.dirname(target), { recursive: true }); fs.writeFileSync(target, contents)
}
const compile = async (id, entries) => {
    const result = await build({ absWorkingDir: root, entryPoints: entries, outdir: `dist/${id}`,
        bundle: true, splitting: true, format: 'esm', minify: true, metafile: true })
    return { result, snapshot: captureModuleBuild(root, id, result.metafile) }
}
try {
    write('resources/js/shared.js', 'globalThis.sharedEvaluations = (globalThis.sharedEvaluations || 0) + 1; export const shared = () => globalThis.sharedEvaluations')
    write('resources/js/heavy.js', 'globalThis.heavyEvaluations = (globalThis.heavyEvaluations || 0) + 1; export const heavy = () => 42')
    write('node_modules/library-v1/index.js', 'export const version = () => "1.0.0"')
    write('node_modules/library-v2/index.js', 'export const version = () => "2.0.0"')
    write('resources/js/a.js', 'export {shared} from "./shared.js"; export {version as one} from "../../node_modules/library-v1/index.js"')
    write('resources/js/b.js', 'export {shared} from "./shared.js"; export {version as two} from "../../node_modules/library-v2/index.js"; export const optional = () => import("./heavy.js")')
    const independentA = await compile('separate-a', ['resources/js/a.js'])
    const independentB = await compile('separate-b', ['resources/js/b.js'])
    const repeated = analyseModuleBuilds([independentA.snapshot, independentB.snapshot])
    assert.equal(repeated.sameSourceRepeated, 1)
    assert.equal(repeated.duplicates[0].input, 'resources/js/shared.js')
    assert.ok(repeated.extraAttributedBytes > 0)
    assert.deepEqual(analyseModuleBuilds([independentB.snapshot, independentA.snapshot]), repeated)
    proofs.push('separate compilations expose attributed repeated source bytes; stable order')

    const unified = await compile('components', ['resources/js/a.js', 'resources/js/b.js'])
    assert.equal(analyseModuleBuilds([unified.snapshot]).sameSourceRepeated, 0)
    assert.deepEqual(Object.keys(unified.snapshot.inputs).filter(name => name.includes('library-')), [
        'node_modules/library-v1/index.js', 'node_modules/library-v2/index.js',
    ])
    // Native ESM runs the shared module once, with both versions and lazy code isolated.
    write('package.json', '{"type":"module"}')
    const a = await import(path.join(root, 'dist/components/a.js'))
    const b = await import(path.join(root, 'dist/components/b.js'))
    assert.equal(a.shared(), 1); assert.equal(b.shared(), 1)
    assert.equal(a.one(), '1.0.0'); assert.equal(b.two(), '2.0.0')
    assert.equal(globalThis.heavyEvaluations, undefined)
    assert.equal((await b.optional()).heavy(), 42); assert.equal(globalThis.heavyEvaluations, 1)
    proofs.push('unified native ESM evaluates one shared source, keeps two incompatible library versions and defers optional module')

    const manifest = createComponentManifest(unified.result.metafile, {}, ['a', 'b'], {})
    const lazyOutput = Object.entries(unified.result.metafile.outputs).find(([, value]) => Object.hasOwn(value.inputs, 'resources/js/heavy.js'))[0]
    assert.ok(!manifest.a.includes(path.basename(lazyOutput)) && !manifest.b.includes(path.basename(lazyOutput)))
    assert.ok(Object.values(manifest.__dynamic_imports__).some(names => names.includes(path.basename(lazyOutput))))
    assert.ok(manifest.__shared_chunks__.includes(path.basename(lazyOutput)))
    proofs.push('dynamic output is published while excluded from both eager closures')

    assert.deepEqual(loadModuleBuild(root, 'components'), unified.snapshot)
    const sourceStat = fs.statSync(path.join(root, 'resources/js/shared.js'))
    const original = fs.readFileSync(path.join(root, 'resources/js/shared.js'))
    write('resources/js/shared.js', original.toString().replace(' + 1', ' + 2'))
    fs.utimesSync(path.join(root, 'resources/js/shared.js'), sourceStat.atime, sourceStat.mtime)
    assert.throws(() => loadModuleBuild(root, 'components'), /Stale/)
    write('resources/js/shared.js', original)
    const output = 'dist/components/a.js', contents = fs.readFileSync(path.join(root, output)), outputStat = fs.statSync(path.join(root, output))
    const changed = Buffer.from(contents); changed[0] = changed[0] === 32 ? 33 : 32
    write(output, changed); fs.utimesSync(path.join(root, output), outputStat.atime, outputStat.mtime)
    assert.throws(() => loadModuleBuild(root, 'components'), /Stale/)
    write(output, contents)
    const mixed = structuredClone(independentB.snapshot)
    mixed.inputs['resources/js/shared.js'].sha256 = '0'.repeat(64)
    assert.throws(() => analyseModuleBuilds([independentA.snapshot, mixed]), /Mixed source generations/)
    proofs.push('stale source/output SHA rejected with same bytes and mtime; mixed build generations rejected')

    const reject = mutation => { const altered = structuredClone(unified.snapshot); mutation(altered); assert.throws(() => analyseModuleBuilds([altered])) }
    reject(x => { x.format = 99 }); reject(x => { x.id = '../escape' }); reject(x => { x.inputs = [] })
    reject(x => { x.inputs = {} }); reject(x => { x.outputs = {} })
    reject(x => { x.inputs['../escape.js'] = x.inputs['resources/js/shared.js'] })
    reject(x => { x.inputs['resources/js/shared.js'].bytes = -1 })
    reject(x => { x.inputs['resources/js/shared.js'].sha256 = 'no' })
    reject(x => { x.outputs[output].inputs.missing = 1 })
    reject(x => { x.outputs[output].inputs['resources/js/a.js'] = Number.MAX_SAFE_INTEGER })
    reject(x => { x.outputs[output].inputs['resources/js/a.js'] = -1 })
    reject(x => { x.outputs[output].inputs['resources/js/a.js'] = 0.5 })
    reject(x => { x.outputs = Object.fromEntries(Array.from({length: 16385}, (_, n) => [`${n}.js`, {}])) })
    assert.throws(() => analyseModuleBuilds([unified.snapshot, unified.snapshot]))
    const collision = structuredClone(unified.snapshot); collision.id = 'collision'
    assert.throws(() => analyseModuleBuilds([unified.snapshot, collision]), /multiply-owned/)
    fs.symlinkSync(path.join(root, 'resources/js/shared.js'), path.join(root, 'resources/js/alias.js'))
    const alias = structuredClone(unified.result.metafile)
    alias.inputs['resources/js/alias.js'] = alias.inputs['resources/js/shared.js']
    assert.throws(() => captureModuleBuild(root, 'alias', alias), /canonical/)
    const escaping = structuredClone(unified.result.metafile)
    escaping.inputs['../../escape.js'] = { bytes: 0 }
    assert.throws(() => captureModuleBuild(root, 'unsafe', escaping))
    assert.ok(!JSON.stringify(unified.snapshot).includes(root))
    assert.equal(fs.readdirSync(path.join(root, 'build/asset-analysis')).filter(name => name.endsWith('.tmp')).length, 0)
    proofs.push('bounded metadata, unsafe paths, unknown edges, aliases, malformed sizes/hashes and duplicate identities/outputs reject')

    write('providers/shared.json', '{"abi":1}')
    write('resources/js/virtual-user.js', 'export {value} from "fixture:shared"')
    const generated = { identity: 'virtual/fixture-v1/shared.js', provider: 'fixture-v1',
        contents: 'export const value = 42', provenance: ['providers/shared.json'] }
    const virtualResult = await build({ absWorkingDir: root, entryPoints: ['resources/js/virtual-user.js'],
        outfile: 'dist/virtual.js', bundle: true, format: 'esm', metafile: true,
        plugins: [{ name: 'fixture', setup(builder) {
            builder.onResolve({ filter: /^fixture:shared$/ }, () => ({ path: 'shared', namespace: 'fixture-virtual' }))
            builder.onLoad({ filter: /.*/, namespace: 'fixture-virtual' }, () => ({ contents: generated.contents, loader: 'js' }))
        } }] })
    assert.throws(() => captureModuleBuild(root, 'unverified-virtual', virtualResult.metafile))
    const capturedVirtual = captureModuleBuild(root, 'virtual', virtualResult.metafile,
        { virtualInputs: new Map([['fixture-virtual:shared', generated]]) })
    const verifier = new Map([[generated.identity, generated]])
    assert.deepEqual(loadModuleBuild(root, 'virtual', { virtualInputs: verifier }), capturedVirtual)
    assert.throws(() => loadModuleBuild(root, 'virtual'), /verifier/)
    assert.throws(() => loadModuleBuild(root, 'virtual', { virtualInputs: new Map([[generated.identity,
        { ...generated, contents: generated.contents.replace('42', '43') }]]) }), /Stale virtual/)
    write('providers/shared.json', '{"abi":2}')
    assert.throws(() => loadModuleBuild(root, 'virtual', { virtualInputs: verifier }), /Stale module/)
    write('providers/shared.json', '{"abi":1}')
    const missingProvenance = structuredClone(capturedVirtual)
    delete missingProvenance.inputs['providers/shared.json']
    assert.throws(() => analyseModuleBuilds([missingProvenance]), /provenance/)
    const forgedProvider = structuredClone(capturedVirtual)
    forgedProvider.inputs[generated.identity].provider = 'different-v1'
    assert.throws(() => analyseModuleBuilds([forgedProvider]), /provenance/)
    proofs.push('actual virtual-module compilation requires explicit provider, generated bytes and pinned physical provenance; missing/stale/forged inputs reject')

    write('resources/dist/a.js', 'export const a = 1')
    write('resources/dist/legacy.js', '/* retained legacy */')
    write('vendor/optional.wasm', Buffer.from([0, 97, 115, 109, 1, 0, 0, 0]))
    const retained = captureOpaqueBuild(root, 'retained', ['resources/dist/legacy.js'], { reason: 'Unattributed fixture output.' })
    assert.deepEqual(loadOpaqueBuild(root, 'retained'), retained)
    const optional = captureOpaqueBuild(root, 'optional', ['vendor/optional.wasm'],
        { reason: 'Optional prebuilt vendor bytes.', provenance: ['providers/shared.json'] })
    assert.deepEqual(loadOpaqueBuild(root, 'optional'), optional)
    const wasm = fs.readFileSync(path.join(root, 'vendor/optional.wasm'))
    const wasmStat = fs.statSync(path.join(root, 'vendor/optional.wasm'))
    write('vendor/optional.wasm', Buffer.from(wasm).fill(2, 7))
    fs.utimesSync(path.join(root, 'vendor/optional.wasm'), wasmStat.atime, wasmStat.mtime)
    assert.throws(() => loadOpaqueBuild(root, 'optional'), /Stale/)
    write('vendor/optional.wasm', wasm)
    assert.throws(() => captureOpaqueBuild(root, 'duplicate-opaque', ['vendor/optional.wasm', 'vendor/optional.wasm'], { reason: 'Duplicate.' }))
    assert.throws(() => captureOpaqueBuild(root, '../escape', ['vendor/optional.wasm'], { reason: 'Unsafe.' }))
    const coverageBuilds = [{ outputs: { 'resources/dist/a.js': {} } }]
    assert.deepEqual(verifyModuleOutputCoverage(root, coverageBuilds, [retained]),
        { attributedJavascript: 1, opaqueDistributedJavascript: 1, sourceAttributionComplete: false })
    assert.throws(() => verifyModuleOutputCoverage(root, coverageBuilds), /missing or unexpected/)
    assert.throws(() => verifyModuleOutputCoverage(root, coverageBuilds, [retained, retained]), /conflicting/)
    write('resources/dist/unlisted.mjs', 'export const unknown = 1')
    assert.throws(() => verifyModuleOutputCoverage(root, coverageBuilds, [retained]), /missing or unexpected/)
    fs.unlinkSync(path.join(root, 'resources/dist/unlisted.mjs'))
    fs.symlinkSync(path.join(root, 'resources/dist/a.js'), path.join(root, 'resources/dist/alias.js'))
    assert.throws(() => verifyModuleOutputCoverage(root, coverageBuilds, [retained]), /symlinks/)
    proofs.push('opaque JS/WASM accounting verifies byte hashes without claiming attribution; full output inventory rejects omissions, added JS, conflicting owners and symlinks')

    const cleanupRoot = path.join(root, 'cleanup')
    const put = (name, bytes) => { const file = path.join(cleanupRoot, name); fs.mkdirSync(path.dirname(file), { recursive: true }); fs.writeFileSync(file, bytes) }
    put('resources/js/a.js', 'export const lazy = () => import("./lazy.js")')
    put('resources/js/lazy.js', 'export const value = 42')
    const cleanupBuild = await build({ absWorkingDir: cleanupRoot, entryPoints: ['resources/js/a.js'],
        outdir: 'resources/dist/components', bundle: true, splitting: true, format: 'esm', metafile: true })
    captureModuleBuild(cleanupRoot, 'components', cleanupBuild.metafile)
    const cleanupManifest = createComponentManifest(cleanupBuild.metafile, {}, ['a'], {})
    put('resources/dist/components/alpine-manifest.json', JSON.stringify(cleanupManifest))
    put('resources/dist/components/retired.js', '/* unreferenced old output */')
    put('resources/dist/filament-flex-fields.js', '/* unregistered legacy output */')
    put('public/js/old-published-chunk.js', '/* open host document */')
    assert.throws(() => pruneComponentOutput(cleanupRoot), /lease/)
    await withComponentOutputLock(cleanupRoot, async lease => {
        await assert.rejects(withComponentOutputLock(cleanupRoot, async () => {}), /live process/)
        fs.symlinkSync(path.join(cleanupRoot, 'public/js/old-published-chunk.js'), path.join(cleanupRoot, 'resources/dist/components/unsafe.js'))
        assert.throws(() => pruneComponentOutput(cleanupRoot, lease), /symlink/)
        assert.ok(fs.existsSync(path.join(cleanupRoot, 'resources/dist/components/retired.js')))
        fs.unlinkSync(path.join(cleanupRoot, 'resources/dist/components/unsafe.js'))
        const current = fs.readFileSync(path.join(cleanupRoot, 'resources/dist/components/a.js'))
        put('resources/dist/components/a.js', Buffer.from(current).fill(32, 0, 1))
        assert.throws(() => pruneComponentOutput(cleanupRoot, lease), /Stale/)
        assert.ok(fs.existsSync(path.join(cleanupRoot, 'resources/dist/components/retired.js')))
        put('resources/dist/components/a.js', current)
        put('resources/dist/components/alpine-manifest.json', JSON.stringify({ ...cleanupManifest, __entries__: ['foreign'] }))
        assert.throws(() => pruneComponentOutput(cleanupRoot, lease), /does not match/)
        put('resources/dist/components/alpine-manifest.json', JSON.stringify(cleanupManifest))
        const removed = pruneComponentOutput(cleanupRoot, lease)
        assert.equal(removed.removed.length, 2)
        assert.deepEqual(removed.removed.map(item => item.path), ['resources/dist/components/retired.js', 'resources/dist/filament-flex-fields.js'])
        assert.equal(pruneComponentOutput(cleanupRoot, lease).removed.length, 0)
        for (const name of Object.keys(cleanupBuild.metafile.outputs)) assert.ok(fs.existsSync(path.join(cleanupRoot, name)))
    })
    assert.ok(fs.existsSync(path.join(cleanupRoot, 'public/js/old-published-chunk.js')))
    const lockPath = path.join(cleanupRoot, 'build/asset-analysis/.component-output.lock')
    assert.ok(!fs.existsSync(lockPath))
    await assert.rejects(withComponentOutputLock(cleanupRoot, async () => { throw Error('simulated failed build') }), /failed build/)
    assert.ok(!fs.existsSync(lockPath))
    const exited = spawnSync(process.execPath, ['-e', 'process.stdout.write(String(process.pid))'], { encoding: 'utf8' })
    assert.equal(exited.status, 0)
    put('build/asset-analysis/.component-output.lock', JSON.stringify({ format: 1, pid: Number(exited.stdout), token: '12345678-1234-1234-1234-123456789abc' }))
    await withComponentOutputLock(cleanupRoot, async lease => assert.equal(pruneComponentOutput(cleanupRoot, lease).removed.length, 0))
    assert.ok(!fs.existsSync(lockPath))
    fs.symlinkSync(path.join(cleanupRoot, 'public/js/old-published-chunk.js'), lockPath)
    await assert.rejects(withComponentOutputLock(cleanupRoot, async () => {}), /Unsafe/)
    fs.unlinkSync(lockPath)
    proofs.push('exclusive build cleanup protects current eager/lazy outputs and published old chunks; live/forged leases, symlinks, stale bytes and wrong manifest reject; failed and exited-process locks recover safely')

    put('resources/css/entries/a.css', '.a {}')
    put('resources/dist/css/a.css', '.a{}')
    put('resources/dist/css/core.css', '.core{}')
    put('resources/dist/css/playground.css', '.demo{}')
    put('resources/dist/css/retired.css', '.retired{}')
    put('resources/dist/filament-flex-fields.css', '.legacy{}')
    await withComponentOutputLock(cleanupRoot, async lease => {
        assert.throws(() => pruneStylesheetOutput(cleanupRoot, [], lease), /every current entry/)
        fs.symlinkSync(path.join(cleanupRoot, 'resources/dist/css/a.css'), path.join(cleanupRoot, 'resources/dist/css/unsafe.css'))
        assert.throws(() => pruneStylesheetOutput(cleanupRoot, ['a.css'], lease), /symlinks/)
        assert.ok(fs.existsSync(path.join(cleanupRoot, 'resources/dist/css/retired.css')))
        fs.unlinkSync(path.join(cleanupRoot, 'resources/dist/css/unsafe.css'))
        assert.equal(pruneStylesheetOutput(cleanupRoot, ['a.css'], lease).removed.length, 2)
        assert.equal(pruneStylesheetOutput(cleanupRoot, ['a.css'], lease).removed.length, 0)
        for (const name of ['a', 'core', 'playground']) assert.ok(fs.existsSync(path.join(cleanupRoot, `resources/dist/css/${name}.css`)))
    })
    proofs.push('full CSS cleanup removes retired outputs and monolithic legacy CSS, preserves current/core/catalog styles, rejects partial builds and unsafe candidates')
    console.log(JSON.stringify({ proofs, duplicatesBefore: repeated.sameSourceRepeated, duplicatesAfter: 0 }))
} finally {
    fs.rmSync(root, { recursive: true, force: true })
    delete globalThis.sharedEvaluations; delete globalThis.heavyEvaluations
}
