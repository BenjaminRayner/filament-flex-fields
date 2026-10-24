import assert from 'node:assert/strict'
import { observeDomAssets } from '../../resources/js/runtime/lifecycle/dom-assets-observer.js'
import { createPendingSurface } from '../../resources/js/runtime/filament/pending-surface.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createElement } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'
import { prepareFilamentPartials } from '../../resources/js/runtime/filament/partial-assets.js'

const observed = []
let disconnected = 0
class Observer {
    constructor(callback) { this.callback = callback }
    observe(body) { observed.push(body) }
    disconnect() { disconnected++ }
}
const connectedBody = () => Object.defineProperty(createElement('body'), 'isConnected', { value: true })
const document = Object.assign(new EventTarget(), { body: connectedBody() })
const window = { MutationObserver: Observer, setTimeout, clearTimeout, requestAnimationFrame: callback => setTimeout(callback, 0), cancelAnimationFrame: clearTimeout }
const lifetime = createResourceScope()
observeDomAssets({ document, window, lifetime, ensureAssets: async () => {}, graph: {}, reportError: error => { throw error } })
const oldBody = document.body
const newBody = connectedBody()
document.body = newBody
document.dispatchEvent(new Event('livewire:navigated'))
assert.deepEqual(observed, [oldBody, newBody], 'Navigation did not reconnect the observer')
lifetime.dispose()
assert(disconnected >= 3)

const panelScope = createElement('div')
panelScope.classList.add('fff-segment-tabs__panel')
const marker = createElement('div')
marker.nodeType = 1
const markerMatches = marker.matches.bind(marker)
marker.matches = selector => selector.split(',').some(part => markerMatches(part.trim()))
marker.setAttribute('data-fff-asset-batch', '')
panelScope.appendChild(marker)
newBody.appendChild(panelScope)
let mutationObserver
const preparedRoots = [], ensuredRoots = [], releasedRoots = []
const panelLifetime = createResourceScope()
observeDomAssets({ document, window: { ...window, MutationObserver: class extends Observer {
    constructor(callback) { super(callback); mutationObserver = this }
} }, lifetime: panelLifetime,
ensureAssets: async root => ensuredRoots.push(root),
preparePanels: root => { preparedRoots.push(root); return () => true },
releasePanels: root => releasedRoots.push(root),
graph: {}, reportError: error => { throw error } })
mutationObserver.callback([{ type: 'attributes', attributeName: 'data-fff-assets-ready', oldValue: '', target: marker }])
assert.deepEqual(preparedRoots, [marker], 'Indexed preparation must inspect the changed child, not rescan unrelated siblings')
await new Promise(resolve => setTimeout(resolve, 0))
assert.deepEqual(ensuredRoots, [marker], 'Indexed preparation must retain the changed subtree as its acquisition root')
assert.deepEqual(releasedRoots, [marker], 'Indexed preparation must settle and release exactly its owned changed subtree')
panelLifetime.dispose()

// Non-indexed adapters retain the explicit independently gated paint root.
const localPaint = createElement('div')
localPaint.setAttribute('data-fff-local-paint', '')
const localMatches = localPaint.matches.bind(localPaint)
localPaint.matches = selector => selector === '[data-fff-local-paint]' || localMatches(selector)
const localMarker = createElement('span')
localMarker.setAttribute('data-fff-asset-batch', '')
const localMarkerMatches = localMarker.matches.bind(localMarker)
localMarker.matches = selector => selector.split(',').some(part => localMarkerMatches(part.trim()))
localPaint.appendChild(localMarker)
panelScope.appendChild(localPaint)
let localObserver, localReleased = 0
const localPrepared = [], localEnsured = [], localLifetime = createResourceScope()
observeDomAssets({ document, window: { ...window, MutationObserver: class extends Observer {
    constructor(callback) { super(callback); localObserver = this }
} }, lifetime: localLifetime, graph: {},
ensureAssets: async root => localEnsured.push(root),
preparePanels: root => { localPrepared.push(root); return () => { localReleased++; return true } },
reportError: error => { throw error } })
localObserver.callback([{ type: 'attributes', attributeName: 'data-fff-assets-ready', oldValue: '', target: localMarker }])
assert.deepEqual(localPrepared, [localPaint], 'Legacy preparation lost its explicit local paint boundary')
await new Promise(resolve => setTimeout(resolve, 0))
assert.deepEqual(localEnsured, [localPaint], 'Legacy acquisition escaped its local paint owner')
assert.equal(localReleased, 1, 'Settled local presentation did not release its own paint barrier')
localMarker.setAttribute('data-fff-assets-ready', '')
localObserver.callback([{ type: 'attributes', attributeName: 'data-fff-assets-ready', oldValue: null, target: localMarker }])
await new Promise(resolve => setTimeout(resolve, 0))
assert.equal(localPrepared.length, 1, 'Successful readiness write triggered another preparation cycle')
localLifetime.dispose()

const dormantPanel = createElement('div')
dormantPanel.classList.add('fff-segment-tabs__panel')
dormantPanel.setAttribute('data-fff-deferred-panel', '')
newBody.appendChild(dormantPanel)
const livePartial = createElement('div')
livePartial.setAttribute('wire:partial', 'deferred-fields')
dormantPanel.appendChild(livePartial)
const partialOwner = { querySelectorAll: () => [livePartial] }
livePartial.closest = selector => selector.includes('wire') ? partialOwner : dormantPanel
const incomingPartial = createElement('div')
const incomingMarker = createElement('div')
incomingMarker.setAttribute('data-fff-asset-batch', '')
incomingPartial.appendChild(incomingMarker)
const partialContainer = createElement('div')
partialContainer.appendChild(incomingPartial)
partialContainer.firstElementChild = incomingPartial
partialContainer.innerHTML = ''
let heldDormantPanel = false, primedDormantAssets = false
prepareFilamentPartials({ message: { component: { el: partialOwner } },
payload: { effects: { partials: { 'deferred-fields': '<div data-fff-asset-batch></div>' } } },
document: { body: newBody, createElement: () => partialContainer },
primePreparedStyles: () => { primedDormantAssets = true },
onDeferredPanel: panel => { heldDormantPanel = panel === dormantPanel } })
assert(heldDormantPanel, 'A deferred response arriving after its tab hides must preserve its loader')
assert(!primedDormantAssets, 'Holding dormant presentation must not prime or acquire dormant requirements')

const target = createElement('div')
newBody.appendChild(target)
const pending = createPendingSurface({ document: { createElement }, window, injectorHooks: { markPendingStarted() {}, getPendingReleaseDelayMs: () => 0 }, resolvePendingTarget: element => element, awaitInflightAssetLoads: () => Promise.resolve() })
pending.applyPendingState(target)
await new Promise(resolve => setTimeout(resolve, 420))
assert(target.classList.contains('fff-flex-fields-assets-pending'), 'Skeleton revealed unready content after 300 ms')
await pending.releasePendingState(target)
assert(!target.classList.contains('fff-flex-fields-assets-pending'))
assert(target.classList.contains('fff-flex-fields-assets-ready'))
const child = createElement('div')
target.appendChild(child)
pending.applyPendingState(child)
assert.deepEqual(pending.getPendingTargets(target), [child], 'Coalesced ancestor lost a pending child')
assert(!pending.hasPendingState(target), 'Ancestor incorrectly inherited child loading ownership')
await Promise.all(pending.getPendingTargets(target).map(node => pending.releasePendingState(node)))
assert(!child.classList.contains('fff-flex-fields-assets-pending'), 'Child stayed blocked after ancestor preparation')
assert.deepEqual(pending.getPendingTargets(target), [])
pending.applyPendingState(target)
pending.dispose()
assert(!target.classList.contains('fff-flex-fields-assets-pending'), 'Disposed surface retained its skeleton')
console.log('PASS: observer follows Livewire body replacement, skeleton remains beyond 300 ms until explicit readiness, disposal releases pending surfaces.')

const { withFactoryReadiness } = await import('../../resources/js/core/flex-fff-load-directive.js')
const componentRoot = Object.assign(new EventTarget(), createElement('div'), {
    dataset: {}, isConnected: true, _x_dataStack: [{}],
    addEventListener: EventTarget.prototype.addEventListener,
    removeEventListener: EventTarget.prototype.removeEventListener,
    dispatchEvent: EventTarget.prototype.dispatchEvent,
})
let finishInitialization
let readyEvents = 0
componentRoot.addEventListener('fff-load-ready', () => readyEvents++)
const component = withFactoryReadiness(() => ({ init() { return new Promise(resolve => { finishInitialization = resolve }) } }))()
component.$el = componentRoot
const initialized = component.init()
assert.equal(componentRoot.dataset.fffAlpineReady, undefined, 'Async init marked ready prematurely')
finishInitialization()
await initialized
assert.equal(componentRoot.dataset.fffAlpineReady, 'true')
assert.equal(readyEvents, 1)
const failed = withFactoryReadiness(() => ({ async init() { throw new Error('Expected diagnostic failure') } }))()
failed.$el = componentRoot
await assert.rejects(failed.init(), /Expected diagnostic failure/)
assert.equal(componentRoot._x_async, 'failed')
assert.equal(componentRoot.dataset.fffAlpineReady, undefined)
console.log('PASS: asynchronous factory init participates in readiness, errors participate in retry state.')

const { getAlpineLoadGate, resetAlpineLoadGateForTests } = await import('../../resources/js/core/flex-alpine-load-gate.js')
resetAlpineLoadGateForTests()
const registrations = new Map()
const previousAlpine = globalThis.Alpine
globalThis.Alpine = { data: (name, factory) => registrations.set(name, factory) }
try {
    const gate = getAlpineLoadGate()
    const source = () => ({ version: 'owned' })
    const identity = { package: 'example/navigation', component: 'diagnostic-select', export: 'default', abi: 1, module: 'https://example.test/diagnostic.js' }
    gate.registerAlpineData('diagnosticSelect', source, identity)
    const nativeRegistration = registrations.get('diagnosticSelect')
    gate.prepareAlpineData('diagnosticSelect', source, identity)
    const state = registrations.get('diagnosticSelect')()
    assert.equal(state.version, 'owned', 'Readiness decoration changed the registered source')
    assert.equal(typeof state.init, 'function', 'Entry registration captured the unwrapped source')
    state.$el = componentRoot
    await state.init()
    assert.equal(componentRoot.dataset.fffAlpineReady, 'true')
    assert.throws(() => gate.prepareAlpineData('diagnosticSelect', () => ({ version: 'foreign' }), identity), /alias conflict/, 'Foreign decoration replaced the typed owner')
    assert.throws(() => gate.registerAlpineData('diagnosticSelect', () => ({ version: 'foreign' }), identity), /alias conflict/, 'Foreign registration replaced the typed owner')
    assert.equal(registrations.get('diagnosticSelect')().version, 'owned', 'A foreign anonymous source replaced the existing owner')
    assert.equal(registrations.get('diagnosticSelect'), nativeRegistration, 'Readiness decoration re-registered the native alias')
    assert.equal(registrations.size, 1)
} finally {
    globalThis.Alpine = previousAlpine
    resetAlpineLoadGateForTests()
}
console.log('PASS: exact-source readiness decoration preserves one native alias and rejects a foreign typed replacement.')
