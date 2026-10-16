import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const fixture = JSON.parse(execFileSync('php', ['scripts/compatibility/core-plan-dictionary.php'], { cwd: root, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 }))
const source = `
import { createPlanDictionary, decodeAssetPlan, readReferencedPlan } from './resources/js/runtime/assets/plan-dictionary.js'
import { createBoundaryPlanReader } from './resources/js/runtime/assets/boundary-plan.js'
import { createConsumerGraph } from './resources/js/runtime/assets/consumer-graph.js'
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js'
import { createAssetDemand } from './resources/js/runtime/assets/asset-demand.js'
import { createBundleRegistry } from './resources/js/runtime/assets/bundle-registry.js'
import { resolveModuleFactoryIdentity } from './resources/js/runtime/alpine/factory-identity.js'
const assert = (ok, message) => { if (!ok) throw Error(message) }
const reject = callback => { try { callback() } catch { return }; throw Error('Invalid plan was accepted') }
window.probeResult = (async () => {
    const fixture = await (await fetch('/fixture')).json(), proofs = []
    const batches = [...document.querySelectorAll('[data-fff-asset-batch]')]
    const queryAll = document.querySelectorAll.bind(document); let planLookups = 0
    document.querySelectorAll = selector => { if (selector.startsWith('[data-fff-plan-definition=')) planLookups++; return queryAll(selector) }
    const started = performance.now(), reader = createBoundaryPlanReader(), first = reader.read(batches[0], 'select-field').plan
    for (const batch of batches) assert(reader.read(batch, 'select-field').plan === first, 'descriptors were parsed separately per owner')
    const readDurationMs = performance.now() - started
    assert(planLookups === 1, 'descriptor lookup rescanned the document per owner')
    document.querySelectorAll = queryAll
    window.planReadProfile = { owners: batches.length, planLookups, readDurationMs }
    assert(batches.length === 1000 && document.querySelectorAll('[data-fff-plan-definition]').length === 1, 'SSR projection changed independent boundaries or duplicated definitions')
    proofs.push('actual Blade render: 1000 independent owner markers, one descriptor and one immutable projection')

    const graph = createConsumerGraph({ normalizeUrl: url => new URL(url, document.baseURI).href })
    graph.resyncFromDom(document)
    assert(graph.getRefCount(new URL('/z-first.css', document.baseURI).href) === 1000, 'referenced plans lost shared stylesheet refcounts')
    const store = createAssetLinkStore({ document, window }), registry = createBundleRegistry()
    const demand = createAssetDemand({ document, window, store, registry, graph, hooks: {}, isActiveBatch: () => true, afterEnsure() {} })
    assert(await demand.ensureAssets(document) === true, 'referenced owners never became ready')
    assert(getComputedStyle(batches[0].parentElement).color === 'rgb(0, 128, 0)', 'CSS server ordering changed')
    proofs.push('production transport: one request per CSS resource, preserved cascade and 1000 independent graph references')

    const container = document.createElement('div'); container.innerHTML = fixture.partial
    const partial = container.querySelector('[data-fff-asset-batch]')
    assert(reader.read(partial, 'select-field').plan === first, 'detached partial lost its dictionary before activation')
    document.body.append(container)
    assert(await demand.awaitBundleReady('select-field', partial.parentElement.querySelector('.field-root')) === true, 'inserted partial did not prepare')
    proofs.push('fresh self-contained detached fragment is read before insertion and reuses prepared transport after activation')

    const dictionary = createPlanDictionary({ limit: 1, byteLimit: 4096 })
    const make = (id, raw) => {
        const owner = document.createElement('section'), batch = document.createElement('span'), template = document.createElement('template'), field = document.createElement('div')
        batch.dataset.fffAssetBatch = ''; batch.dataset.fffAssetConsumer = 'probe'; batch.dataset.fffAssetConsumerId = 'probe-' + id
        batch.dataset.fffPlanId = id; batch.dataset.fffPlanGeneration = '1'
        template.dataset.fffPlanDefinition = id; template.content.append(document.createTextNode(raw)); batch.append(template)
        owner.append(batch, field); return { owner, batch, template, field }
    }
    const factory = make(fixture.id, fixture.plan), factoryPlan = dictionary.read(factory.batch)
    assert(Object.isFrozen(factoryPlan) && Object.isFrozen(factoryPlan.sizes) && Object.isFrozen(factoryPlan.factoryOwners), 'descriptor metadata is mutable')
    assert(resolveModuleFactoryIdentity(factory.field, 'probe', '/helper.js', {}).package === 'example/probe', 'factory identity bypassed the referenced plan')
    document.body.append(factory.owner)
    assert(await demand.awaitBundleReady('probe', factory.field) === true, 'referenced module descriptor was not evaluated')
    proofs.push('factory ownership, module readiness and immutable byte metadata use the same referenced descriptor')

    const changedValue = { ...JSON.parse(fixture.plan), stylesheets: ['/other.css'] }; changedValue.sizes = {}; changedValue.factoryOwners = {}
    const changed = make('b'.repeat(64), JSON.stringify(changedValue)); dictionary.read(changed.batch)
    assert(dictionary.inspect().plans === 1 && dictionary.read(factory.batch) === factoryPlan, 'catalog eviction invalidated the mounted marker projection')
    reject(() => dictionary.admit('b'.repeat(64), fixture.plan, document.baseURI))
    factory.batch.dataset.fffPlanId = 'b'.repeat(64); factory.batch.dataset.fffPlanGeneration = '2'
    assert(dictionary.read(factory.batch).stylesheets[0].endsWith('/other.css'), 'generation handover retained stale plan')
    proofs.push('bounded eviction preserves mounted owners; conflicting admission rejects and generation changes replace projections')

    const missing = make('c'.repeat(64), fixture.plan); missing.template.remove()
    reject(() => reader.read(missing.batch, 'probe'))
    changed.batch.dataset.fffStylesheets = '[]'; reject(() => dictionary.read(changed.batch)); delete changed.batch.dataset.fffStylesheets
    changed.batch.dataset.fffAssetConsumer = 'wrong'; reject(() => dictionary.read(changed.batch))
    for (const value of [
        { ...JSON.parse(fixture.plan), format: 2 },
        { ...JSON.parse(fixture.plan), chunks: ['javascript:bad'] },
        { ...JSON.parse(fixture.plan), stylesheets: ['//evil.test/x.css'] },
        { ...JSON.parse(fixture.plan), sizes: { '/undeclared.js': 10 } },
        { ...JSON.parse(fixture.plan), factoryOwners: { '/helper.js': { package: 'example/probe', component: 'probe', export: 'default', abi: 2 } } },
    ]) reject(() => decodeAssetPlan(JSON.stringify(value), document.baseURI))
    proofs.push('unknown plans, mixed transport, component mismatch, invalid URLs/sizes and ABI fail before acquisition')

    // A dictionary first discovered at initial paint must still see a new plan
    // inserted later into the same document without a global observer or rescan.
    const late = make('d'.repeat(64), JSON.stringify(changedValue)); document.body.append(late.owner)
    assert(readReferencedPlan(late.batch).stylesheets[0].endsWith('/other.css'), 'late definition remained invisible after initial document admission')
    proofs.push('later document definitions become available without creating another observer or transport runtime')
    demand.dispose(); graph.reset(); reader.clear(); dictionary.clear()
    window.probeFinished = true; return proofs
})().catch(error => { window.probeFailure = error.message; throw error })
`
const output = await build({ stdin: { contents: source, resolveDir: root }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const requests = []
const server = createServer((request, response) => {
    requests.push(request.url)
    const send = (kind, body) => { response.writeHead(200, { 'Content-Type': kind, 'Cache-Control': 'no-store' }); response.end(body) }
    if (request.url === '/') return send('text/html', '<!doctype html><html><head><meta charset="utf-8"></head><body>' + fixture.html + '<script type="module" src="/probe.js"></script></body></html>')
    if (request.url === '/probe.js') return send('text/javascript', output.outputFiles[0].contents)
    if (request.url === '/fixture') return send('application/json', JSON.stringify(fixture))
    if (request.url === '/z-first.css') return send('text/css', '.ordered-proof { color: rgb(128, 0, 0) }')
    if (request.url === '/a-last.css') return send('text/css', '.ordered-proof { color: rgb(0, 128, 0) }')
    if (request.url === '/helper.js') return send('text/javascript', 'export default () => ({ init() {} })')
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.probeFailure || window.probeFinished, null, { timeout: 10000 })
    const proofs = await page.evaluate(() => window.probeResult)
    const profile = await page.evaluate(() => window.planReadProfile)
    assert.deepEqual(errors, []); assert.equal(proofs.length, 7)
    for (const resource of ['/z-first.css', '/a-last.css', '/helper.js']) assert.equal(requests.filter(url => url === resource).length, 1)
    console.log(JSON.stringify({ browser: await browser.version(), minifiedSourceBuild: true, phpProofs: fixture.proofs, owners: fixture.owners, descriptors: fixture.descriptors,
        dictionaryBytes: fixture.dictionaryBytes, inlineBytes: fixture.inlineBytes, publicDictionaryBytes: fixture.publicDictionaryBytes, publicInlineBytes: fixture.publicInlineBytes,
        publicLargeDictionaryBytes: fixture.publicLargeDictionaryBytes, publicLargeInlineBytes: fixture.publicLargeInlineBytes,
        publicLargeReduction: fixture.publicLargeReduction, profile, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
