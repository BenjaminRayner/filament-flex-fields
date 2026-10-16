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
const preparedRoots = [], ensuredRoots = []
const panelLifetime = createResourceScope()
observeDomAssets({ document, window: { ...window, MutationObserver: class extends Observer {
    constructor(callback) { super(callback); mutationObserver = this }
} }, lifetime: panelLifetime,
ensureAssets: async root => ensuredRoots.push(root),
preparePanels: root => { preparedRoots.push(root); return () => true },
graph: {}, reportError: error => { throw error } })
mutationObserver.callback([{ type: 'attributes', attributeName: 'data-fff-assets-ready', oldValue: '', target: marker }])
assert.equal(preparedRoots[0], panelScope, 'Revoked child readiness must prepare the whole FLEX panel before the next paint')
await new Promise(resolve => setTimeout(resolve, 0))
assert.equal(ensuredRoots[0], panelScope, 'Panel sibling requirements must complete together')
panelLifetime.dispose()

const dormantPanel = createElement('div')
dormantPanel.classList.add('fff-segment-tabs__panel')
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
const partialContainer = { firstElementChild: incomingPartial, innerHTML: '' }
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
const componentRoot = Object.assign(new EventTarget(), { dataset: {}, isConnected: true, _x_dataStack: [{}] })
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
    gate.registerAlpineData('diagnosticSelect', () => ({ version: 'entry' }))
    gate.registerAlpineData('diagnosticSelect', withFactoryReadiness(() => ({ version: 'wrapped' })))
    const state = registrations.get('diagnosticSelect')()
    assert.equal(state.version, 'wrapped', 'Entry registration captured a stale factory')
    state.$el = componentRoot
    await state.init()
    assert.equal(componentRoot.dataset.fffAlpineReady, 'true')
    assert.equal(registrations.size, 1)
} finally {
    globalThis.Alpine = previousAlpine
    resetAlpineLoadGateForTests()
}
console.log('PASS: entry self-registration uses the latest readiness factory without duplicate Alpine registrations.')
