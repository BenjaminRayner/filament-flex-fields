import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { pathToFileURL } from 'node:url'
import { build } from 'esbuild'
import { createFlexSdkPlugin, assertFlexSdkExternalization } from '../assets/flex-sdk-plugin.mjs'
import { createEsbuildPackageSpecification } from '../assets/esbuild-package-graph.mjs'
import { installRuntimeSDK } from '../../resources/js/sdk/v1.js'

const root = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-sdk-'))
const privateSource = path.resolve(import.meta.dirname, '../../resources/js/runtime/lifecycle/resource-scope.js')
const proofs = []
const compile = (contents, overrides = {}) => build({ absWorkingDir: root, stdin: { contents, resolveDir: root }, outdir: path.join(root, 'dist'),
    bundle: true, format: 'esm', minify: true, metafile: true, write: false, plugins: [createFlexSdkPlugin()], logLevel: 'silent', ...overrides })
try {
    const result = await compile(`import {createResourceScope, createOwnedSubscriptions} from '@bjanczak/flex-fields/sdk/v1';
export const scope = () => createResourceScope(); export const subscriptions = options => createOwnedSubscriptions(options); export default () => ({init(){}});`)
    for (const output of result.outputFiles) { fs.mkdirSync(path.dirname(output.path), { recursive: true }); fs.writeFileSync(output.path, output.contents) }
    assert.deepEqual(Object.keys(result.metafile.inputs).sort(), ['<stdin>', 'fff-runtime-sdk:v1'])
    const declaration = { format: 1, package: 'example/sdk-field', entries: { 'sdk-field': 'stdin.js' }, styles: {}, helpers: {}, types: {}, resources: {} }
    const specification = createEsbuildPackageSpecification(result.metafile, declaration, { workingDirectory: root, outputRoot: path.join(root, 'dist') })
    assert.throws(() => createEsbuildPackageSpecification({ outputs: result.metafile.outputs }, declaration, { workingDirectory: root, outputRoot: path.join(root, 'dist') }), /input metadata/)
    assert.equal(Object.keys(specification.artifacts).length, 1)
    assert.deepEqual(specification.artifacts[specification.entries['sdk-field']].imports, [])
    const serialized = JSON.stringify(specification)
    assert.ok(!serialized.includes(root) && !serialized.includes('fff-runtime-sdk') && !serialized.includes('bjanczak/flex-fields'))
    proofs.push('actual esbuild SDK facade has no private inputs or external browser edges; portable package graph needs no host URL/import map')

    await assert.rejects(compile(`import {createResourceScope} from '@bjanczak/flex-fields/sdk/v2'; export {createResourceScope};`), /Unsupported FLEX SDK/)
    await assert.rejects(compile(`import {privateRuntime} from '@bjanczak/flex-fields/sdk/v1'; export {privateRuntime};`), /No matching export/)
    await assert.rejects(compile('export default () => ({})', { metafile: false }), /metafile: true/)
    await assert.rejects(compile('export default () => ({})', { write: true }), /write: false/)
    await assert.rejects(compile(`export {createResourceScope} from ${JSON.stringify(privateSource)};`), /Do not bundle FLEX/)
    const copied = await build({ absWorkingDir: root, stdin: { contents: `export {createResourceScope} from ${JSON.stringify(privateSource)};`, resolveDir: root },
        outdir: path.join(root, 'bad-dist'), bundle: true, format: 'esm', metafile: true, write: false, logLevel: 'silent' })
    assert.throws(() => createEsbuildPackageSpecification(copied.metafile, declaration, { workingDirectory: root, outputRoot: path.join(root, 'bad-dist') }), /Do not bundle FLEX/)
    const alias = path.join(root, 'alias.js'); fs.symlinkSync(privateSource, alias)
    assert.throws(() => assertFlexSdkExternalization({ inputs: { 'alias.js': {} } }, { workingDirectory: root }), /Do not bundle FLEX/)
    proofs.push('unsupported ABI/private exports, missing metadata, embedded runtime and symlink aliases are rejected by plugin/compiler')

    fs.writeFileSync(path.join(root, 'package.json'), '{"type":"module"}')
    const author = await import(pathToFileURL(path.join(root, 'dist/stdin.js')).href)
    const key = Symbol.for('fff.sdk.v1')
    assert.throws(() => author.scope(), /ABI 1 is not installed/)
    globalThis[key] = { sdkABI: 2 }
    assert.throws(() => author.scope(), /ABI 1 is not installed/)
    assert.throws(() => installRuntimeSDK(), /Conflicting/)
    delete globalThis[key]
    const sdk = installRuntimeSDK()
    assert.equal(installRuntimeSDK(), sdk)
    assert.ok(Object.isFrozen(sdk))
    assert.throws(() => { globalThis[key] = {} }, TypeError)
    const scope = author.scope(); let disposed = 0
    scope.defer(() => disposed++); scope.dispose(); scope.dispose()
    assert.equal(disposed, 1)
    const subscriptions = author.subscriptions({ names: ['changed'] })
    let changed = 0
    const unsubscribe = subscriptions.subscribe({ changed: () => changed++ })
    subscriptions.notify('changed'); unsubscribe(); subscriptions.notify('changed'); subscriptions.dispose()
    assert.equal(changed, 1)
    proofs.push('native author ESM fails explicitly without compatible SDK; installed immutable ABI is idempotent; actual owned cleanup/subscriptions share core implementation')
    console.log(JSON.stringify({ proofs, sdkABI: sdk.sdkABI, embeddedRuntimeInputs: 0 }))
} finally { fs.rmSync(root, { recursive: true, force: true }) }
