import assert from 'node:assert/strict'
import test from 'node:test'
import fs from 'node:fs'
import { createHash } from 'node:crypto'
import { distribution, startupCases, startupFields, startupBudgets, startupResourcePolicy, assertStartupResourceBudget, summarizeStartup } from '../../scripts/core/startup-cost.mjs'

const manifestBytes = fs.readFileSync(new URL('../../resources/dist/components/alpine-manifest.json', import.meta.url))
const seal = JSON.parse(fs.readFileSync(new URL('../../resources/dist/asset-versions.json', import.meta.url)))
const policy = startupResourcePolicy(manifestBytes, seal)
const measured = paths => paths.map(path => ({ path, flex: true, local: true, hasQuery: false, hasFragment: false, decoded: 100, transfer: 0, initiator: 'script' }))

const observations = () => ['fresh-context', 'primed-context'].flatMap(mode => startupCases.map(name => ({
    case: name, mode, sample: 0, boundaries: startupFields[name], ready: startupFields[name], errors: [],
    domContentLoadedMs: 20, boundaryReadyMs: 30, longTasks: [], resources: [
        { local: true, flex: false, decoded: 1000, transfer: 1300 },
        ...(name === 'native' ? [] : [{ local: true, flex: true, decoded: 120, transfer: mode === 'fresh-context' ? 420 : 0 }]),
    ],
})))

test('startup cost distinguishes FLEX payload, HTTP transfer and native resources', () => {
    const result = summarizeStartup(observations(), 1)
    assert.equal(Object.keys(result).length, 10)
    assert.equal(result['fresh-context/native'].flexResourceEntries.median, 0)
    assert.equal(result['fresh-context/one'].flexDecodedBytes.median, 120)
    assert.equal(result['fresh-context/one'].flexTransferBytes.median, 420)
    assert.equal(result['primed-context/one'].flexTransferBytes.median, 0)
    assert.equal(result['primed-context/one'].flexDecodedBytes.median, 120)
})

test('cost distributions preserve even medians and nearest-rank p95', () => {
    assert.deepEqual(distribution([40, 10, 20, 30]), { min: 10, median: 25, p95: 40, max: 40 })
    assert.equal(distribution(Array.from({ length: 20 }, (_, i) => i + 1)).p95, 19)
    for (const values of [[], [NaN], [Infinity], [-1]]) assert.throws(() => distribution(values))
})

for (const [name, change] of [
    ['missing sample', samples => samples.pop()],
    ['duplicate case', samples => { samples[1] = { ...samples[0] } }],
    ['wrong sample index', samples => { samples[0].sample = 1 }],
    ['unready field', samples => { samples[2].ready = 0 }],
    ['incorrect boundary count', samples => { samples[2].boundaries = 20 }],
    ['browser error', samples => { samples[2].errors.push('factory failed') }],
    ['external resource', samples => { samples[2].resources[0].local = false }],
    ['invalid payload bytes', samples => { samples[2].resources[1].decoded = -1 }],
]) test('startup report rejects ' + name, () => {
    const samples = observations(); change(samples)
    assert.throws(() => summarizeStartup(samples, 1))
})

test('renderer phase and heap counters remain distinct and reject unavailable measurements as numbers', () => {
    const samples = observations().map(sample => ({ ...sample, renderer: { scriptMs: 2, taskMs: 5, layoutMs: 1, styleMs: 1,
        heapUsedBytes: 1000, heapTotalBytes: 2000, domNodes: 300, eventListeners: 20 } }))
    const result = summarizeStartup(samples, 1)['fresh-context/hundred']
    assert.equal(result.renderer.scriptMs.median, 2)
    assert.equal(result.renderer.heapUsedBytes.median, 1000)
    samples[0].renderer.layoutMs = NaN
    assert.throws(() => summarizeStartup(samples, 1))
})

test('only the sealed load-gate fallback transports extend the unchanged regular request ceiling', () => {
    assert.equal(startupBudgets.active.resources, 22)
    assert.equal(startupBudgets.active.decodedBytes, 220 * 1024)
    assert.equal(policy.fallback.length, 2)
    const facade = policy.fallback.find(path => JSON.parse(manifestBytes).__chunk_modules__[path.split('/').at(-1)].length === 0)
    const entries = measured([...policy.regular, facade])
    const result = assertStartupResourceBudget(entries, true, policy)
    assert.equal(result.rawResourceEntries, policy.regular.length + 1)
    assert.equal(result.regularResourceEntries, policy.regular.length)
    assert.equal(result.fallbackResourceEntries, 1)
    assert.deepEqual(result.fallbackResources, [facade])
    assert.equal(assertStartupResourceBudget(measured([...policy.regular, ...policy.fallback]), true, policy).fallbackResourceEntries, 2)
    const samples = observations()
    samples.find(sample => sample.case === 'one' && sample.mode === 'fresh-context').resources = entries
    assert.equal(summarizeStartup(samples, 1)['fresh-context/one'].flexResourceEntries.median, entries.length)
})

test('startup budget rejects unrelated extras, repeated fallback requests and noncanonical aliases', () => {
    assert.throws(() => assertStartupResourceBudget(measured([...policy.regular, policy.regular[0].replace(/[^/]+$/, 'unrelated.js')]), true, policy), /outside the sealed startup graph/)
    assert.throws(() => assertStartupResourceBudget(measured([...policy.regular, policy.fallback[0], policy.fallback[0]]), true, policy), /Duplicate/)
    for (const property of ['hasQuery', 'hasFragment']) {
        const entries = measured([...policy.regular, policy.fallback[0]])
        entries.at(-1)[property] = true
        assert.throws(() => assertStartupResourceBudget(entries, true, policy), /canonical URL/)
    }
    const otherRelease = policy.fallback[0].replace(seal.release, 'a'.repeat(64))
    assert.throws(() => assertStartupResourceBudget(measured([...policy.regular, otherRelease]), true, policy), /outside the sealed startup graph/)
})

test('fallback allowance never changes the empty-panel or decoded-byte ceilings', () => {
    assert.deepEqual(startupBudgets.empty, { resources: 3, decodedBytes: 34 * 1024 })
    assert.throws(() => assertStartupResourceBudget(measured(policy.regular.slice(0, 4)), false, policy), /regular resource-entry budget/)
    assert.throws(() => assertStartupResourceBudget(measured([policy.fallback[0]]), false, policy), /outside the sealed startup graph/)
    const entries = measured([...policy.regular, policy.fallback[0]])
    entries.at(-1).decoded = startupBudgets.active.decodedBytes + 1
    assert.throws(() => assertStartupResourceBudget(entries, true, policy), /decoded-byte budget/)
})

test('startup fallback policy rejects unsealed or changed manifest topology', () => {
    assert.throws(() => startupResourcePolicy(Buffer.from('{}'), seal), /not bound to the sealed release/)
    const malformed = mutate => {
        const manifest = JSON.parse(manifestBytes)
        mutate(manifest)
        const bytes = Buffer.from(JSON.stringify(manifest))
        const bound = { ...seal, metadata: { ...seal.metadata,
            'resources/dist/components/alpine-manifest.json': createHash('sha256').update(bytes).digest('hex') } }
        return () => startupResourcePolicy(bytes, bound)
    }
    const gate = manifest => Object.keys(manifest.__chunk_modules__).find(name => manifest.__chunk_modules__[name].includes('runtime/alpine/load-gate.js'))
    assert.throws(malformed(manifest => { manifest.__dynamic_imports__[gate(manifest)].push(manifest.__shared_chunks__[0]) }), /transport set changed|dependencies/)
    assert.throws(malformed(manifest => { manifest.__dynamic_imports__[gate(manifest)] = [manifest.__dynamic_imports__[gate(manifest)][0]] }), /transport set changed/)
    assert.throws(malformed(manifest => {
        const facade = manifest.__dynamic_imports__[gate(manifest)].find(name => manifest.__chunk_modules__[name].length === 0)
        manifest.__chunk_modules__[facade] = ['unreviewed/new-transport.js']
    }), /re-export facade/)
})
