import assert from 'node:assert/strict'
import { build } from 'esbuild'
import { createAssetRuntime } from '../../resources/js/runtime/asset-runtime-engine.js'
import { createDomRuntimeAdapter } from '../../resources/js/runtime/surfaces/dom-runtime-adapter.js'
import { createFlexFieldAssetInjector } from '../../resources/js/runtime/asset-runtime.js'
import { customSurfacesFor } from '../../resources/js/runtime/surfaces/document-surfaces.js'
import { getRuntimeState } from '../../resources/js/runtime/runtime-state.js'
import { changesAssetActivation } from '../../resources/js/runtime/lifecycle/activation-mutation.js'
import { changesFilamentActivation } from '../../resources/js/runtime/filament/activation-mutation.js'
import { createDom, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

// Existing native suites cover modal behavior. These acceptance cases cover the
// newly separate adapter constructor, graph and framework-free DOM lifecycle.
const proofs = [], unhandled = []
process.on('unhandledRejection', error => unhandled.push(String(error)))
const settle = async () => { for (let i = 0; i < 24; i++) await Promise.resolve() }
{
    const mutation = { attributeName: 'class', oldValue: 'fi-sc-loading', target: { classList: { contains: () => false }, getAttribute: () => '' } }
    assert.equal(changesAssetActivation(mutation), false)
    assert.equal(changesFilamentActivation(mutation), true)
    mutation.oldValue = 'fi-active'
    assert.equal(changesAssetActivation(mutation), false)
    assert.equal(changesFilamentActivation(mutation), true)
    mutation.oldValue = 'hidden'
    assert.equal(changesAssetActivation(mutation), true)
    proofs.push('shared DOM mutation semantics contain no native activation/loading vocabulary; native adapter supplies its explicit policy')
}
function fixture() {
    const dom = createDom(), listeners = new Map(), observations = new Set(), frames = new Map(), timers = new Map()
    let sequence = 0
    for (const target of [dom.document, dom.window]) {
        const events = new EventTarget(), entries = new Map(); listeners.set(target, entries)
        target.addEventListener = (name, callback, options) => { entries.set(callback, name); events.addEventListener(name, callback, options) }
        target.removeEventListener = (name, callback, options) => { entries.delete(callback); events.removeEventListener(name, callback, options) }
        target.dispatchEvent = event => events.dispatchEvent(event)
    }
    dom.window.MutationObserver = class {
        observe(root) { this.root = root; observations.add(this) }
        disconnect() { observations.delete(this) }
    }
    dom.window.setTimeout = callback => { const id = ++sequence; timers.set(id, callback); return id }
    dom.window.clearTimeout = id => timers.delete(id)
    dom.window.requestAnimationFrame = callback => { const id = ++sequence; frames.set(id, callback); return id }
    dom.window.cancelAnimationFrame = id => frames.delete(id)
    const field = (parent = dom.body) => {
        const element = dom.document.createElement('section'); element.ownerDocument = dom.document
        element.appendChild(createAssetBatch([], [], { consumerComponent: 'external' })); parent.appendChild(element)
        return element
    }
    const quiet = () => {
        assert.equal([...listeners.values()].reduce((sum, entries) => sum + entries.size, 0), 0)
        assert.equal(observations.size, 0); assert.equal(frames.size, 0); assert.equal(timers.size, 0)
        assert.equal(customSurfacesFor(dom.document), null)
        assert(!getRuntimeState().assetStores?.has(dom.document)); assert(!getRuntimeState().diagnostics?.has(dom.document))
    }
    return { ...dom, field, listeners, observations, quiet }
}
{
    const graph = await build({ entryPoints: ['resources/js/runtime/asset-runtime-engine.js'], bundle: true, write: false, format: 'esm', metafile: true, logLevel: 'silent' })
    assert(!Object.keys(graph.metafile.inputs).some(name => name.includes('/filament/') || name.includes('fff-load-directive') || name.includes('/presentation/')))
    proofs.push('standalone engine compilation includes zero native Filament/Livewire adapter modules, domain bridges or Alpine directive side effects')
}
{
    const f = fixture(), runtime = createAssetRuntime(f)
    f.window.Livewire = { hook() { throw Error('unrequested native hook') } }
    const element = f.field(); element.classList.add('fi-sc-tabs-tab')
    assert.equal(runtime.isElementActive(element), true)
    element.hidden = true; assert.equal(runtime.isElementActive(element), false); element.hidden = false
    element.setAttribute('data-fff-asset-active', 'false'); assert.equal(runtime.isElementActive(element), false)
    element.setAttribute('data-fff-asset-active', 'true'); runtime.boot(); await settle()
    assert.equal(runtime.prepareModal, undefined)
    assert(![...f.listeners.values()].flatMap(entries => [...entries.values()]).some(name => /livewire|modal|segment/.test(name)))
    assert.equal(await runtime.ensureAssets(element), true)
    runtime.dispose(); await settle(); f.quiet()
    proofs.push('default DOM engine honors explicit activation and hidden state, ignores unrelated native class names, exposes no modal API and attaches no native events/hooks')
}
{
    const f = fixture(), runtime = createAssetRuntime(f), parent = f.field(), child = f.field(parent)
    parent.hidden = true; child.hidden = true
    const a = runtime.registerAssetSurface(parent), b = runtime.registerAssetSurface(child, { parent: a })
    assert.equal(await b.activate(), false); assert.equal(await a.activate(), true); a.suspend()
    assert.equal(await b.activate(), true); assert.notEqual(runtime.resolveAssetOwnerKey(child), runtime.resolveAssetOwnerKey(parent))
    b.close(); assert(runtime.isElementActive(parent)); a.close(); assert(!runtime.isElementActive(parent))
    a.dispose(); runtime.dispose(); await settle(); f.quiet()
    proofs.push('framework-free nested surfaces retain suspended parent ownership; child close never retires parent and parent close deactivates descendants')
}
{
    const f = fixture(), root = f.field(); root.classList.add('fi-modal')
    const runtime = createAssetRuntime(f), owner = runtime.registerAssetSurface(root)
    assert.equal(await owner.activate(), true)
    owner.dispose(); runtime.dispose(); await settle(); f.quiet()
    const native = createFlexFieldAssetInjector(f)
    assert.throws(() => native.registerAssetSurface(root), /Invalid or duplicate FLEX surface root/)
    native.dispose(); await settle(); f.quiet()
    proofs.push('headless custom surfaces ignore native class names; native facade reserves its own modal roots without putting framework vocabulary into shared ownership')
}
{
    const f = fixture(), runtime = createAssetRuntime(f); runtime.boot(); await settle()
    const body = f.document.createElement('body'); f.document.body = body; body.parentElement = f.document
    // Lightweight fixture marks document connectivity independently of a browser.
    Object.defineProperty(body, 'isConnected', { value: true })
    const field = f.field(body); assert.equal(await runtime.refreshDocument(), true)
    assert([...f.observations].some(observer => observer.root === body))
    assert.equal(await runtime.ensureAssets(field), true); runtime.dispose(); await settle(); f.quiet()
    proofs.push('explicit document refresh reconnects the DOM observer after body replacement without requiring any framework navigation event')
}
{
    for (const phase of ['factory', 'async-factory', 'contract', 'activation-policy', 'connect', 'async-connect', 'connection', 'observer-policy', 'boot']) {
        const f = fixture(); let cleanups = 0
        const createAdapter = context => {
            context.lifetime.listen(f.window, 'author-owned', () => {})
            context.lifetime.defer(() => cleanups++)
            if (phase === 'factory') throw Error('factory')
            if (phase === 'async-factory') return Promise.reject(Error('late factory'))
            if (phase === 'contract') return {}
            const base = createDomRuntimeAdapter(context)
            if (phase === 'activation-policy') base.factoryEvents.changesActivation = true
            base.connect = () => {
                context.lifetime.listen(f.document, 'author-connect', () => {})
                if (phase === 'connect') throw Error('connect')
                if (phase === 'async-connect') return Promise.reject(Error('late connect'))
                if (phase === 'connection') return {}
                if (phase === 'observer-policy') return { api: {}, observerOptions: { changesActivation: true }, beforeRefresh() {}, boot() {} }
                return { api: {}, observerOptions: { navigationEvent: null }, beforeRefresh() {}, boot() { throw Error('boot') } }
            }
            return base
        }
        if (phase === 'boot') {
            const runtime = createAssetRuntime({ ...f, createAdapter }); assert.throws(runtime.boot, /boot/); assert(runtime.isDisposed())
        } else assert.throws(() => createAssetRuntime({ ...f, createAdapter }), /factory|contract|connect|synchronous/)
        assert.equal(cleanups, 1); await settle(); f.quiet()
        const retry = createAssetRuntime(f); retry.boot(); retry.dispose(); await settle(); f.quiet()
    }
    proofs.push('sync/async factory and connection, contract and boot faults dispose all already owned adapter effects exactly once; a fresh runtime can start after each rejected construction')
}
{
    const f = fixture(), runtime = createFlexFieldAssetInjector(f)
    assert.equal(typeof runtime.prepareModal, 'function'); assert.equal(typeof runtime.handleLivewireNavigated, 'function')
    runtime.boot(); assert([...f.listeners.get(f.document).values()].includes('livewire:init'))
    runtime.dispose(); await settle(); f.quiet()
    proofs.push('historical native facade explicitly selects its native adapter and preserves modal/navigation compatibility; disposal removes its framework effects')
}
await settle(); assert.deepEqual(unhandled, [])
console.log(JSON.stringify({ groups: proofs.length, proofs, unhandled, testsModified: false }, null, 2))
