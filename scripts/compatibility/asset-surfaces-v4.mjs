import assert from 'node:assert/strict'
import { createConsumerGraph } from '../../resources/js/runtime/assets/consumer-graph.js'
import { createSurfaceRegistry } from '../../resources/js/runtime/filament/surface-registry.js'
import { isAssetElementActive, waitForAssetActivation } from '../../resources/js/runtime/lifecycle/asset-activation.js'
import { createAssetDemand } from '../../resources/js/runtime/assets/asset-demand.js'
import { createBundleRegistry } from '../../resources/js/runtime/assets/bundle-registry.js'
import { createLivewireAdapter } from '../../resources/js/runtime/filament/livewire-adapter.js'
import { createElement, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const body = createElement('body')
body.parentElement = {}
const document = { body, baseURI: 'https://fixture.test/', querySelectorAll: selector => body.querySelectorAll(selector) }
const surfaces = createSurfaceRegistry({ document })
const isActive = element => isAssetElementActive(element, { document, surfaces })
const registry = createBundleRegistry()
const retired = []
const requests = []
const loaded = new Set()
const graph = createConsumerGraph({ registry: registry.bundles, isConsumerActive: isActive, resolveSurfaceKey: surfaces.resolveAssetOwnerKey,
    normalizeUrl: value => new URL(value, document.baseURI).href,
    onAcquire(_id, _component, urls) { for (const url of urls) graph.onRefZero(url, value => { retired.push(value); loaded.delete(value) }) },
})
const store = { discover() {}, registerSizes() {}, isLoaded: url => loaded.has(new URL(url, document.baseURI).href),
    async load(url, kind) { if (!loaded.has(url)) { requests.push([url, kind]); loaded.add(url) } },
}
const demand = createAssetDemand({ document, window: {}, registry, graph, store, isActiveBatch: isActive,
    hooks: { shouldBatchTriggerPending: (batch, fn) => fn(batch) }, afterEnsure() {} })
const element = (parent, ...classes) => { const node = createElement('div'); node.classList.add(...classes); parent.appendChild(node); return node }
const batch = (parent, id, urls) => { const node = createAssetBatch(urls.filter(url => url.endsWith('.css')), urls.filter(url => url.endsWith('.js')), { consumerComponent:id, livewireKey:id }); parent.appendChild(node); return node }
const shared = '/shared.css'
const page = element(body)
batch(page, 'page-select', [shared, '/page.css', '/page.js'])
const tabs = element(body)
const first = element(tabs, 'fi-sc-tabs-tab', 'fi-active')
const second = element(tabs, 'fi-sc-tabs-tab')
batch(first, 'first', ['/first.css', shared])
batch(second, 'second', ['/second.css', shared, '/second.js'])
const modals = Array.from({ length:5 }, (_, index) => {
    const modal = element(body, 'fi-modal'); modal.id = `modal-${index + 1}`
    const window = element(modal, 'fi-modal-window')
    batch(window, `modal-${index + 1}`, [shared, `/modal-${index + 1}.css`, `/modal-${index + 1}.js`])
    return modal
})
await demand.ensureAssets(body)
assert.equal(graph.getRefCount(shared), 2)
assert(!requests.some(([url]) => /second|modal/.test(url)), 'Hidden scopes fetched eagerly')
assert(!demand.rootNeedsAssetLoading(modals[0]), 'Closed modal blocked the page')
first.classList.remove('fi-active'); second.classList.add('fi-active')
await demand.ensureAssets(tabs)
assert.equal(graph.getRefCount('/first.css'), 0)
assert.equal(graph.getRefCount(shared), 2)
assert.equal(graph.getRefCount('/second.css'), 1)
const returningMarker = second.querySelector('[data-fff-asset-batch]')
returningMarker.removeAttribute('data-fff-assets-ready')
const returning = demand.ensureAssets(tabs)
assert.equal(returningMarker.getAttribute('data-fff-assets-ready'), '', 'Retained CSS must restore readiness synchronously on tab return')
await returning
await new Promise(resolve => setTimeout(resolve, 180))
assert(retired.some(url => url.endsWith('/first.css')))
for (let index=0; index<5; index++) {
    if (index) { modals[index-1].classList.remove('fi-modal-open'); modals[index-1].querySelector('.fi-modal-window').style.display = 'none' }
    surfaces.pushModalOpenStack(surfaces.resolveModalOwnerKey(modals[index]))
    modals[index].classList.add('fi-modal-open')
    await demand.ensureAssets(body)
    assert.equal(graph.getRefCount(shared), 3 + index)
    for (let parent=0;parent<=index;parent++) assert.equal(graph.getRefCount(`/modal-${parent+1}.css`), 1)
}
for (let index=4;index>=0;index--) {
    surfaces.popModalOpenStack(surfaces.resolveModalOwnerKey(modals[index]))
    await demand.ensureAssets(body)
    assert.equal(graph.getRefCount(`/modal-${index+1}.css`), 0, 'Lingering transition class retained a closed modal')
    assert.equal(graph.getRefCount(shared), 2 + index)
}
await new Promise(resolve => setTimeout(resolve, 180))
assert.equal(graph.getRefCount('/page.css'), 1)
assert.equal(surfaces.modalOpenStack.length, 0)
assert([...loaded].every(url => !/modal-/.test(url)))
// Native/custom hiding contract and same-key repeated table cells.
const table = element(body)
const row1 = element(table), row2 = element(table)
batch(row1, 'column', ['/column.css']); batch(row2, 'column', ['/column.css'])
await demand.ensureAssets(table); assert.equal(graph.getRefCount('/column.css'), 2)
row1.remove(); graph.releaseSubtree(row1); assert.equal(graph.getRefCount('/column.css'), 1)
table.setAttribute('data-fff-asset-active', 'false'); await demand.ensureAssets(table)
assert.equal(graph.getRefCount('/column.css'), 0)
table.removeAttribute('data-fff-asset-active'); await demand.ensureAssets(table)
assert.equal(graph.getRefCount('/column.css'), 1)
row2.style.display = 'none'; await demand.ensureAssets(table); assert.equal(graph.getRefCount('/column.css'), 0)
row2.style.display = ''; await demand.ensureAssets(table); assert.equal(graph.getRefCount('/column.css'), 1)
// SegmentTabs hides panels with its own is-active class, not inline styles.
const segmentGroup = element(body)
const segmentOne = element(segmentGroup), segmentTwo = element(segmentGroup)
segmentOne.classList.add('fff-segment-tabs__panel', 'is-active')
segmentTwo.classList.add('fff-segment-tabs__panel')
batch(segmentOne, 'segment-one', ['/segment-one.css'])
batch(segmentTwo, 'segment-two', ['/segment-two.css'])
await demand.ensureAssets(segmentGroup)
assert.equal(graph.getRefCount('/segment-one.css'), 1)
assert.equal(graph.getRefCount('/segment-two.css'), 0, 'Dormant SegmentTab acquired CSS')
segmentOne.classList.remove('is-active'); segmentTwo.classList.add('is-active')
await demand.ensureAssets(segmentGroup)
assert.equal(graph.getRefCount('/segment-one.css'), 0)
assert.equal(graph.getRefCount('/segment-two.css'), 1)
// Cancellation never imports a dormant field or leaves a waiting promise alive.
const dormant = element(second); second.classList.remove('fi-active')
dormant.ownerDocument = document
const controller = new AbortController()
const waiting = waitForAssetActivation(dormant, { signal: controller.signal })
controller.abort(); assert.equal(await waiting, false)
// Updating a plan on the same node releases old optional resources.
const marker = row2.querySelector('[data-fff-asset-batch]')
marker.setAttribute('data-fff-stylesheets', JSON.stringify(['/column-new.css']))
await demand.ensureAssets(table)
assert.equal(graph.getRefCount('/column.css'), 0)
assert.equal(graph.getRefCount('/column-new.css'), 1)
graph.reset()
console.log('PASS: actual demand planner + DOM graph: inactive/active tabs, 5 suspended modal owners, LIFO teardown, shared CSS/JS, repeated table cells, last-row removal, native/custom hiding, cancelled activation and per-instance plan changes.')

// Readiness belongs to prepared requirements, not the SSR attribute copied by a morph.
const incoming = createElement('div')
const preserved = batch(incoming, 'page-select', [shared, '/page.css'])
const missing = batch(incoming, 'new-plan', ['/not-loaded.css'])
const incomingDormant = batch(incoming, 'incomingDormant', [shared])
incomingDormant.setAttribute('data-fff-asset-active', 'false')
missing.setAttribute('data-fff-assets-ready', '')
demand.primePreparedStyles(incoming, el => el !== incomingDormant)
assert.equal(preserved.getAttribute('data-fff-assets-ready'), '', 'Prepared CSS must survive a server marker refresh before paint')
assert.equal(missing.getAttribute('data-fff-assets-ready'), null, 'Changed requirements must not inherit stale readiness')
assert.equal(incomingDormant.getAttribute('data-fff-assets-ready'), null, 'Inactive incoming consumers must remain lazy')
console.log('PASS: pre-paint morph readiness preserves prepared CSS, rejects stale changed requirements and keeps inactive incoming consumers lazy.')

// Exercise the actual Livewire hooks: readiness must be present when the hook
// returns, before Livewire copies incoming attributes or inserts a new subtree.
const registeredHooks = new Map()
const disposers = []
const adapter = createLivewireAdapter({ document,
    window: { Livewire: { hook(name, callback) { registeredHooks.set(name, callback); return () => registeredHooks.delete(name) } } },
    lifetime: { defer(callback) { disposers.push(callback) } },
    assets: { ...demand, resolvePendingTarget: el => el },
    pending: { applyPendingState() { assert.fail('Prepared CSS should not hide the existing form') } },
    surfaces, crg: graph, injectorHooks: { shouldBatchTriggerPending: (_batch, needsLoading) => needsLoading(_batch) },
})
adapter.registerLivewireHooks()
preserved.removeAttribute('data-fff-assets-ready')
registeredHooks.get('morph.updating')({ el: page.querySelector('[data-fff-asset-batch]'), toEl: preserved })
assert.equal(preserved.getAttribute('data-fff-assets-ready'), '', 'Updating must synchronously preserve prepared readiness')
const addedRoot = createElement('div')
const addedReady = batch(addedRoot, 'added-select', [shared])
const hiddenParent = element(addedRoot)
hiddenParent.style.display = 'none'
const addedHidden = batch(hiddenParent, 'hidden-select', [shared])
const addedMissing = batch(addedRoot, 'added-new', ['/not-loaded.css'])
registeredHooks.get('morph.adding')({ el: addedRoot })
assert.equal(addedReady.getAttribute('data-fff-assets-ready'), '')
assert.equal(addedHidden.getAttribute('data-fff-assets-ready'), null)
assert.equal(addedMissing.getAttribute('data-fff-assets-ready'), null)

const liveOverflow = element(body)
liveOverflow.setAttribute('data-fff-segment-overflow', '')
liveOverflow.dataset.ssrScrollPositioned = 'true'
liveOverflow.setAttribute('data-right-scroll', 'true')
const incomingOverflow = createElement('div')
incomingOverflow.setAttribute('data-fff-segment-overflow', '')
incomingOverflow.classList.add('fff-segment-scroll-shadow--preparing')
registeredHooks.get('morph.updating')({ el: liveOverflow, toEl: incomingOverflow })
assert.equal(incomingOverflow.dataset.ssrScrollPositioned, 'true')
assert(!incomingOverflow.classList.contains('fff-segment-scroll-shadow--preparing'), 'Morph restarted segment first-position hiding')
assert.equal(incomingOverflow.getAttribute('data-right-scroll'), 'true', 'Morph removed a mounted segment fade')
const newOverflow = createElement('div')
newOverflow.setAttribute('data-fff-segment-overflow', '')
newOverflow.classList.add('fff-segment-scroll-shadow--preparing')
registeredHooks.get('morph.updating')({ el: element(body), toEl: newOverflow })
assert.equal(newOverflow.dataset.ssrScrollPositioned, undefined, 'New overflow skipped initial positioning')
assert(newOverflow.classList.contains('fff-segment-scroll-shadow--preparing'))

// Loading a newly rendered child must not mask already ready page/tab chrome.
// The marker gate protects the child; a modal still gates its whole window.
const pendingOwners = []
const morphAdapter = createLivewireAdapter({ document, window: {}, lifetime: { defer() {} },
    assets: { ...demand, resolvePendingTarget: el => el.closest('.fi-modal') ?? el },
    pending: { applyPendingState(el) { pendingOwners.push(el) } },
    surfaces, crg: graph, injectorHooks: { shouldBatchTriggerPending: (_batch, check) => check(_batch) },
})
const liveChrome = element(body)
const incomingChrome = createElement('div')
batch(incomingChrome, 'ready-chrome', [shared])
const incomingNewChild = element(incomingChrome)
batch(incomingNewChild, 'new-code', ['/not-loaded.css', '/not-loaded.js'])
morphAdapter.beginPendingMorph({ el: liveChrome, toEl: incomingChrome })
assert.equal(pendingOwners.length, 0, 'A child plan hid its ready ancestor')
const liveChild = element(liveChrome)
morphAdapter.beginPendingMorph({ el: liveChild, toEl: incomingNewChild })
assert.deepEqual(pendingOwners, [liveChild], 'Changed component did not keep its own readiness surface')
liveChild.setAttribute('wire:ignore', '')
morphAdapter.beginPendingMorph({ el: liveChild, toEl: incomingNewChild })
assert.equal(pendingOwners.length, 1, 'Discarded wire:ignore markup masked the live Alpine tree')
const liveModal = element(body, 'fi-modal')
surfaces.pushModalOpenStack(surfaces.resolveModalOwnerKey(liveModal))
const liveWindow = element(liveModal, 'fi-modal-window')
morphAdapter.beginPendingMorph({ el: liveWindow, toEl: incomingChrome })
assert.equal(pendingOwners.at(-1), liveWindow, 'Modal stopped awaiting descendant requirements')
surfaces.popModalOpenStack(surfaces.resolveModalOwnerKey(liveModal))
for (const dispose of disposers) dispose()
assert.equal(registeredHooks.size, 0, 'Livewire hook subscriptions must dispose with the runtime')
console.log('PASS: actual Livewire updating/adding hooks prime CSS synchronously without a form skeleton, preserve lazy scopes and dispose their subscriptions.')
console.log('PASS: morph loading scopes remain per renderer on pages, respect wire:ignore, and retain whole-window modal readiness.')
