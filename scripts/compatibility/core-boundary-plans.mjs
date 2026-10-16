import assert from 'node:assert/strict'
import { createBoundaryPlanReader } from '../../resources/js/runtime/assets/boundary-plan.js'
import { createBundleRegistry, readAssetUrls } from '../../resources/js/runtime/assets/bundle-registry.js'
import { createAssetDemand } from '../../resources/js/runtime/assets/asset-demand.js'
import { resolveFffLoadComponentId } from '../../resources/js/core/flex-fff-load-directive.js'
import { createDom, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

let checks = 0
const pass = name => { checks++; console.log(`PASS: ${name}`) }
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { resolve, reject, promise } }
const ticks = async () => { for (let index = 0; index < 16; index++) await Promise.resolve() }
function fixture() {
    const dom = createDom()
    const frames = new Map(), requests = new Map(), loaded = new Set(), acquired = []
    let sequence = 0, after = 0, active = true
    dom.window.requestAnimationFrame = callback => { frames.set(++sequence, callback); return sequence }
    dom.window.cancelAnimationFrame = id => frames.delete(id)
    const store = { disposed: false, discover() {}, registerSizes() {},
        isLoaded: url => loaded.has(new URL(url, dom.document.baseURI).href),
        load(url, kind) {
            const key = kind + ':' + new URL(url, dom.document.baseURI).href
            if (!requests.has(key)) requests.set(key, deferred())
            return requests.get(key).promise
        },
    }
    const registry = createBundleRegistry()
    const options = { ...dom, store, registry, graph: { resyncFromDom: root => acquired.push(root) }, hooks: {}, isActiveBatch: () => active, afterEnsure: () => { after++ } }
    const demand = createAssetDemand(options)
    const field = (styles, chunks = [], parent = dom.body) => {
        const owner = dom.document.createElement('section'), element = dom.document.createElement('div')
        const batch = createAssetBatch(styles, chunks, { consumerComponent: 'probe' })
        owner.appendChild(batch); owner.appendChild(element); parent.appendChild(owner)
        return { owner, element, batch }
    }
    return { ...dom, demand, registry, store, options, field, frames, requests, acquired,
        resolve(url, kind = 'stylesheet') { const href = new URL(url, dom.document.baseURI).href; loaded.add(href); requests.get(kind + ':' + href)?.resolve() },
        fail(url) { requests.get('stylesheet:' + new URL(url, dom.document.baseURI).href)?.reject(Error('transport failed')) },
        frame() { const callbacks = [...frames.values()]; frames.clear(); for (const callback of callbacks) callback() },
        setActive(value) { active = value }, get after() { return after },
    }
}
{
    const f = fixture(), parent = f.field(['/parent.css']), child = f.field(['/child.css'], [], parent.owner)
    parent.batch.dataset.fffAssetConsumer = 'schedule-field'
    child.batch.dataset.fffAssetConsumer = 'timezone-field'
    child.element.setAttribute('x-fff-load-src', '/components/select-field.js')
    assert.equal(resolveFffLoadComponentId(child.element), 'timezone-field')
    assert.equal(resolveFffLoadComponentId(parent.element), 'schedule-field')
    f.demand.dispose()
    pass('nested composed factory resolves nearest instance identity independently of its module filename')
}
{
    const f = fixture(), optional = f.field(['/base.css', '/optional.css']), plain = f.field(['/base.css'])
    f.registry.discover(f.document)
    assert.equal(f.registry.bundles.probe.kind, 'ambiguous')
    assert.deepEqual(f.registry.bundles.probe.stylesheets, [])
    const nested = f.field(['/child.css'], ['/child.js'], plain.owner)
    const pending = f.demand.awaitBundleReady('probe', plain.element)
    assert.equal(f.requests.size, 1)
    assert.ok([...f.requests.keys()][0].endsWith('/base.css'))
    assert.deepEqual(f.acquired, [plain.batch])
    f.resolve('/base.css'); await ticks(); f.frame(); await ticks()
    assert.equal(await pending, true)
    assert.equal(optional.batch.getAttribute('data-fff-assets-ready'), null)
    assert.equal(nested.batch.getAttribute('data-fff-assets-ready'), null)
    f.demand.dispose()
    pass('optional/plain instances and nested children never broaden isolated readiness or acquisition')
}
{
    const f = fixture(); f.field(['/optional.css'])
    await assert.rejects(f.demand.awaitBundleReady('probe'), /instance asset boundary/)
    await assert.rejects(f.demand.awaitBundleReady('probe', f.body), /instance asset boundary/)
    assert.equal(f.requests.size, 0); f.demand.dispose()
    pass('missing owner and page-level owner fail before transport')
}
{
    const f = fixture(), field = f.field(['/a.css'])
    const second = createAssetBatch(['/b.css'], [], { consumerComponent: 'probe' }); field.owner.appendChild(second)
    await assert.rejects(f.demand.awaitBundleReady('probe', field.element), /Conflicting/)
    assert.equal(f.requests.size, 0); f.demand.dispose()
    pass('conflicting direct declarations reject atomically instead of guessing a plan')
}
{
    const f = fixture(), field = f.field(['/a.css']), reader = createBoundaryPlanReader()
    for (const raw of ['{', '{}', '[17]', '["javascript:alert(1)"]', JSON.stringify(Array(513).fill('/a.css')), JSON.stringify(['/' + 'x'.repeat(4097)]), ' '.repeat(131073)]) {
        field.batch.setAttribute('data-fff-chunks', raw)
        assert.throws(() => reader.read(field.element, 'probe', f.document.baseURI), /FLEX/)
        assert.throws(() => readAssetUrls(field.batch, 'data-fff-chunks'), /FLEX/)
        assert.throws(() => f.registry.discover(field.owner), /FLEX/)
    }
    field.batch.setAttribute('data-fff-chunks', '["data:text/javascript,alert(1)"]')
    await assert.rejects(f.demand.awaitBundleReady('probe', field.element), /HTTP/)
    assert.equal(f.requests.size, 0); reader.clear(); f.demand.dispose()
    pass('malformed/oversized lists and invalid URL protocols reject before any resource request')
}
{
    const f = fixture(), a = f.field(['/z.css', '/a.css', '/z.css']), b = f.field(['/z.css', '/a.css'])
    const reader = createBoundaryPlanReader({ limit: 2, byteLimit: 4096 })
    const first = reader.read(a.element, 'probe', f.document.baseURI).plan
    assert.equal(first, reader.read(b.element, 'probe', f.document.baseURI).plan)
    assert.ok(Object.isFrozen(first) && Object.isFrozen(first.stylesheets) && Object.isFrozen(first.chunks))
    assert.throws(() => first.stylesheets.push('/injected.css'))
    assert.ok(first.stylesheets[0].endsWith('/z.css'))
    b.batch.setAttribute('data-fff-stylesheets', '["/a.css", "/z.css"]')
    assert.notEqual(first, reader.read(b.element, 'probe', f.document.baseURI).plan)
    a.batch.setAttribute('data-fff-stylesheets', '["/new.css"]')
    const next = reader.read(a.element, 'probe', f.document.baseURI).plan
    assert.notEqual(next, first); assert.ok(first.stylesheets.some(url => url.endsWith('/a.css')))
    for (let index = 0; index < 50; index++) {
        a.batch.setAttribute('data-fff-stylesheets', JSON.stringify([`/${index}.css`]))
        reader.read(a.element, 'probe', f.document.baseURI)
    }
    assert.equal(reader.inspect().plans, 2); assert.ok(reader.inspect().bytes <= 4096)
    assert.equal(reader.read(b.element, 'probe', 'https://other.test/').plan.stylesheets[0], 'https://other.test/a.css')
    reader.clear(); assert.equal(reader.inspect().bytes, 0); f.demand.dispose()
    pass('equivalent immutable projections share a bounded pool; CSS order, marker/base-URL changes preserve distinct identities')
}
{
    const f = fixture(), field = f.field(['/a.css'])
    f.setActive(false)
    assert.equal(await f.demand.awaitBundleReady('probe', field.element), false)
    assert.equal(f.requests.size, 0); f.demand.dispose()
    pass('a dormant instance has no fallback demand')
}
for (const failure of [false, true]) {
    const f = fixture(), field = f.field(['/a.css'])
    const old = f.demand.awaitBundleReady('probe', field.element)
    field.batch.setAttribute('data-fff-stylesheets', '["/b.css"]')
    const current = f.demand.awaitBundleReady('probe', field.element)
    if (failure) f.fail('/a.css'); else f.resolve('/a.css')
    assert.equal(await old, false)
    assert.equal(field.batch.getAttribute('data-fff-assets-ready'), null)
    f.resolve('/b.css'); await ticks(); f.frame(); await ticks()
    assert.equal(await current, true); f.demand.dispose()
    pass(`isolated readiness rejects stale plan ${failure ? 'failure' : 'success'} after morph`)
}
{
    const f = fixture(), field = f.field(['/a.css']), second = createAssetDemand(f.options)
    const a = f.demand.awaitBundleReady('probe', field.element), b = second.awaitBundleReady('probe', field.element)
    f.resolve('/a.css'); await ticks(); f.frame(); await ticks()
    assert.deepEqual(await Promise.all([a, b]), [true, true])
    assert.equal(field.batch.getAttribute('data-fff-assets-ready'), '')
    f.demand.dispose(); f.frame()
    assert.equal(field.batch.getAttribute('data-fff-assets-ready'), '')
    second.dispose(); assert.equal(f.frames.size, 0)
    pass('bootstrap/injector share one paint lease; disposing one adapter preserves the other')
}
{
    const f = fixture(), field = f.field(['/a.css'])
    const old = f.demand.awaitBundleReady('probe', field.element)
    f.demand.dispose()
    field.batch.setAttribute('data-fff-stylesheets', '["/b.css"]')
    const next = createAssetDemand(f.options), current = next.awaitBundleReady('probe', field.element)
    f.resolve('/b.css'); await ticks(); f.frame(); await ticks()
    assert.equal(await current, true)
    f.resolve('/a.css'); assert.equal(await old, false)
    assert.equal(field.batch.getAttribute('data-fff-assets-ready'), '')
    next.dispose()
    pass('disposed document coordinator cannot invalidate a newly retained coordinator')
}
console.log(`PASS: ${checks} boundary-plan groups; no test files modified.`)
