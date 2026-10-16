import assert from 'node:assert/strict'
import { createFlexFieldAssetInjector, bootFlexFieldAssetInjector } from '../../resources/js/runtime/asset-runtime.js'
import { createAssetLinkStore } from '../../resources/js/runtime/assets/asset-link-store.js'
import { createPrefetchEngine } from '../../resources/js/runtime/assets/prefetch-engine.js'
import { runtimeReadiness } from '../../resources/js/runtime/alpine/runtime-readiness.js'
import { getRuntimeState } from '../../resources/js/runtime/runtime-state.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createSegmentPanelPaint } from '../../resources/js/runtime/filament/segment-panel-paint.js'
import { createDom } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const proofs = [], unhandled = [], capture = error => unhandled.push(error)
const settle = async () => { for (let i = 0; i < 12; i++) await Promise.resolve() }
function fixture() {
    const dom = createDom(), listeners = new Map(), observing = new Set(), timers = new Map(), frames = new Map()
    let id = 0
    for (const target of [dom.document, dom.window]) {
        const events = new EventTarget(), entries = new Map(); listeners.set(target, entries)
        target.addEventListener = (name, callback, options) => { entries.set(callback, name); events.addEventListener(name, callback, options) }
        target.removeEventListener = (name, callback, options) => { entries.delete(callback); events.removeEventListener(name, callback, options) }
        target.dispatchEvent = event => events.dispatchEvent(event)
    }
    class Observer {
        constructor() { observing.add(this) }
        observe() { observing.add(this) }
        unobserve() {}
        disconnect() { observing.delete(this) }
    }
    dom.window.MutationObserver = Observer; dom.window.ResizeObserver = Observer; dom.window.IntersectionObserver = Observer
    dom.window.setTimeout = callback => { const key = ++id; timers.set(key, callback); return key }
    dom.window.clearTimeout = key => timers.delete(key)
    dom.window.requestAnimationFrame = callback => { const key = ++id; frames.set(key, callback); return key }
    dom.window.cancelAnimationFrame = key => frames.delete(key)
    dom.window.CustomEvent = class extends Event { constructor(name, options) { super(name); this.detail = options.detail } }
    const quiet = () => {
        assert.equal([...listeners.values()].reduce((total, entries) => total + entries.size, 0), 0)
        assert.equal(observing.size, 0); assert.equal(timers.size, 0); assert.equal(frames.size, 0)
        assert(!getRuntimeState().assetStores?.has(dom.document)); assert(!getRuntimeState().diagnostics?.has(dom.document))
    }
    return { ...dom, listeners, observing, timers, frames, quiet }
}
process.on('unhandledRejection', capture)
try {
    const pending = fixture(); pending.document.readyState = 'loading'
    for (let cycle = 0; cycle < 1000; cycle++) {
        const runtime = bootFlexFieldAssetInjector(pending)
        assert.equal([...pending.listeners.get(pending.document).values()].filter(name => name === 'DOMContentLoaded').length, 1)
        assert.equal(bootFlexFieldAssetInjector(pending), runtime)
        assert.equal(typeof runtime.explainOwner, 'function')
        runtime.dispose(); runtime.dispose(); pending.document.dispatchEvent(new Event('DOMContentLoaded'))
        assert.equal(runtime.explainOwner(null).status, 'disposed')
        pending.quiet(); assert(!pending.window.__fffFlexFieldAssetInjector)
    }
    const manual = bootFlexFieldAssetInjector(pending); manual.boot()
    assert(![...pending.listeners.get(pending.document).values()].includes('DOMContentLoaded'))
    manual.dispose(); pending.quiet()
    proofs.push('1000 dispose-before-DOM-ready cycles remove the owned wait and return listeners/observers/timers/store/diagnostics to baseline; early explicit boot removes the wait')

    for (const fault of ['construction-listener', 'body-observe', 'prefetch-listener', 'native-hook', 'ready-listener']) {
        const dom = fixture(), releases = [], callbacks = []
        const add = dom.document.addEventListener, NativeObserver = dom.window.MutationObserver
        dom.document.addEventListener = (name, callback, options) => {
            add(name, callback, options)
            if (name === ({ 'construction-listener': 'fff-segment-before-change', 'prefetch-listener': 'mouseover', 'ready-listener': 'DOMContentLoaded' })[fault]) throw Error(fault)
        }
        if (fault === 'body-observe') dom.window.MutationObserver = class extends NativeObserver {
            observe() { super.observe(); this.failed = true; throw Error(fault) }
            disconnect() { super.disconnect(); if (this.failed) throw Error('isolated cleanup failure') }
        }
        if (fault === 'ready-listener') dom.document.readyState = 'loading'
        if (fault === 'native-hook') dom.window.Livewire = {
            interceptMessage(callback) { callbacks.push(callback); return () => { releases.push('intercept'); throw Error('isolated cleanup failure') } },
            hook(name, callback) { callbacks.push(callback); if (name === 'morph.adding') throw Error(fault); return () => releases.push(name) },
        }
        assert.throws(() => bootFlexFieldAssetInjector(dom), new RegExp(fault))
        dom.quiet(); assert(!dom.window.__fffFlexFieldAssetInjector); assert(!dom.window.FffAssetInjector)
        if (fault === 'native-hook') assert.deepEqual(releases, ['morph.updating', 'intercept'])
        dom.document.addEventListener = add; dom.window.MutationObserver = NativeObserver; dom.document.readyState = 'complete'; dom.window.Livewire = null
        const fresh = bootFlexFieldAssetInjector(dom)
        assert.equal(bootFlexFieldAssetInjector(dom), fresh)
        for (const callback of callbacks) callback({ el: dom.body, toEl: dom.body, message: {}, onSuccess() { throw Error('obsolete hook') } })
        fresh.dispose(); await settle(); dom.quiet()
    }
    proofs.push('construction, observe-after-attachment, prefetch listener, partial native hooks and DOM-ready listener failures roll back the whole runtime through cleanup failures; fresh boot succeeds and obsolete hooks remain inert')

    const delayed = fixture(), runtime = bootFlexFieldAssetInjector(delayed)
    delayed.window.FffAssetInjector = runtime
    delayed.window.Livewire = { hook() { throw Error('late hook') } }
    delayed.document.dispatchEvent(new Event('livewire:init'))
    assert(runtime.isDisposed()); assert(!delayed.window.FffAssetInjector); delayed.quiet()
    delayed.window.Livewire = null
    const fresh = bootFlexFieldAssetInjector(delayed); fresh.dispose(); delayed.quiet()
    proofs.push('late Livewire init failure retires globals and all already-started effects without an unhandled event exception; retry attaches a fresh runtime')

    const shared = fixture(), client = createAssetLinkStore(shared)
    const loading = client.load('/css/janczakb/filament-flex-fields/shared-startup.css', 'stylesheet')
    await settle()
    const link = shared.head.children.find(node => node.rel === 'stylesheet')
    shared.window.Livewire = { hook() { throw Error('native hook') } }
    assert.throws(() => bootFlexFieldAssetInjector(shared), /native hook/)
    assert(!client.disposed && link.isConnected)
    link.dispatchEvent('load'); await loading
    assert(client.isLoaded(link.href, 'stylesheet')); client.dispose(); shared.quiet()
    proofs.push('a failed runtime releases only its asset-store client; another owner finishes the same in-flight stylesheet and remains usable')

    const independent = fixture(), a = createFlexFieldAssetInjector(independent), b = createFlexFieldAssetInjector(independent)
    independent.window.__fffFlexFieldAssetInjector = b; independent.window.FffAssetInjector = b
    a.dispose(); assert.equal(independent.window.__fffFlexFieldAssetInjector, b); assert.equal(independent.window.FffAssetInjector, b)
    assert(runtimeReadiness().snapshot().published); b.boot(); b.dispose(); independent.quiet()
    assert(!runtimeReadiness().snapshot().published)
    proofs.push('retiring an older runtime preserves the newer runtime globals and readiness publication; final owner releases the bridge')

    const spec = fixture(); let loads = 0
    const prefetch = createPrefetchEngine({ ...spec, shouldSkipBackgroundPreload: () => false, isScopeActive: () => true,
        preloadBatchesIn: () => loads++ })
    let notify
    spec.window.IntersectionObserver = class { constructor(callback) { notify = callback } observe() {} unobserve() {} disconnect() {} }
    prefetch.boot(); notify([{ isIntersecting: true, target: spec.body }]); prefetch.disconnect(); await settle()
    assert.equal(loads, 0); spec.quiet()
    proofs.push('speculation queued before a failed/retired start cannot begin loading in the later microtask')

    let busy = false, resized, refreshes = 0, disconnected = 0
    const cleanupErrors = [], life = createResourceScope({ onCleanupError: error => cleanupErrors.push(error) })
    const panels = Array.from({ length: 2 }, () => {
        const attributes = new Map()
        return { isConnected: true, attributes, classList: { contains: name => ['fff-segment-tabs__panel', 'is-active'].includes(name) },
            matches: selector => selector === '.fff-segment-tabs__panel', querySelector: () => null, querySelectorAll: () => [],
            getBoundingClientRect: () => ({ height: 300 }), setAttribute: (key, value) => attributes.set(key, value),
            removeAttribute: key => attributes.delete(key), getAttribute: key => attributes.get(key) ?? null,
            style: { setProperty() {}, removeProperty() {} } }
    })
    const paint = createSegmentPanelPaint({ document: { body: {}, querySelectorAll: () => panels }, lifetime: life,
        window: { ResizeObserver: class { constructor(callback) { resized = callback } observe() {} unobserve() {} disconnect() { disconnected++ } } },
        needsLoading: () => false, readiness: { refresh() {}, refreshOutstanding() { refreshes++ },
            status: () => ({ batches: 1, assets: 0, factories: busy ? 1 : 0, loaders: 0 }) } })
    busy = true; const baseline = life.snapshot().resources
    for (let cycle = 0; cycle < 1000; cycle++) {
        const release = paint.prepare(panels[0]); assert(panels[0].attributes.has('data-fff-panel-preparing'))
        release(true); assert.equal(life.snapshot().resources, baseline)
    }
    paint.prepare(panels[0]); paint.prepare(panels[1])
    panels[0].style.removeProperty = () => { throw Error('panel cleanup') }
    life.dispose(); const count = refreshes; resized([{ target: panels[0] }])
    assert.equal(refreshes, count); assert.equal(cleanupErrors.length, 1); assert.equal(disconnected, 1)
    assert(!panels[1].attributes.has('data-fff-panel-preparing')); assert.equal(life.snapshot().resources, 0)
    proofs.push('1000 panel prepare/release cycles release their individual cleanup interests; a throwing panel cleanup cannot block its sibling or observer disposal and late resize callbacks stay inert')

    await new Promise(resolve => setTimeout(resolve, 10)); assert.deepEqual(unhandled, [])
    console.log(JSON.stringify({ groups: proofs.length, proofs, unhandled: 0, testsModified: false }))
} finally { process.removeListener('unhandledRejection', capture) }
