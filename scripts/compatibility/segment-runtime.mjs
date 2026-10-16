import assert from 'node:assert/strict'
import { isMountedFactoryReady, preserveMountedFactoryReadiness } from '../../resources/js/core/alpine-readiness.js'
import { withFactoryReadiness } from '../../resources/js/core/flex-fff-load-directive.js'
import control from '../../resources/js/components/segment-control.js'
import tabs from '../../resources/js/components/segment-tabs.js'
import { preserveSegmentPresentation, scrollSegmentItemIntoView, positionSegmentOverflowScrollElement } from '../../resources/js/core/segment-overflow-position.js'

const frames = new Map()
let sequence = 0
let resizeConstructed = 0
let resizeDisposed = 0
let mutationDisposed = 0
let initialized = 0
globalThis.requestAnimationFrame = fn => { const id = ++sequence; frames.set(id, fn); return id }
globalThis.cancelAnimationFrame = id => frames.delete(id)
globalThis.getComputedStyle = element => ({ direction: element.rtl ? 'rtl' : 'ltr' })
globalThis.ResizeObserver = class { constructor() { resizeConstructed++ } observe() {} disconnect() { resizeDisposed++ } }
globalThis.MutationObserver = class { observe() {} disconnect() { mutationDisposed++ } }
const flush = () => { for (const [id, fn] of [...frames]) { frames.delete(id); fn() } }
const classList = () => {
    const values = new Set()
    return { add: (...names) => names.forEach(name => values.add(name)), remove: name => values.delete(name), contains: name => values.has(name), toggle(name, enabled) { if (enabled) values.add(name); else values.delete(name) } }
}
const button = (value, disabled = false) => ({
    dataset: { segmentValue: String(value), segmentSelected: 'false' }, disabled, hidden: false, style: {}, offsetWidth: 80, offsetHeight: 32,
    offsetLeft: Number(value) * 80 || 0, offsetTop: 4, attrs: { role: 'radio' }, focus() { this.focused = true },
    matches: selector => selector === '[data-segment-value]',
    getAttribute(name) { return this.attrs[name] ?? null }, setAttribute(name, value) { this.attrs[name] = value },
})
function mount(component, buttons) {
    const watches = new Map()
    const track = { offsetWidth: 240, classList: classList(), contains: button => buttons.includes(button), querySelectorAll: selector => selector === '[data-segment-value]' ? buttons : [] }
    Object.assign(component, { $refs: { track, indicator: { classList: classList(), style: {} } }, $el: { isConnected: true }, $dispatch() {}, $nextTick: fn => Promise.resolve().then(fn), $watch: (key, callback) => { initialized++; watches.set(key, callback) } })
    return { component, watches, buttons }
}
const field = mount(control({ state: 0, optionKeys: [0, 1, 2] }), [button(0), button(1, true), button(2)])
const first = field.component.init()
assert.equal(first, field.component.init(), 'init must be single-flight')
await first
assert.equal(initialized, 1)
assert.equal(resizeConstructed, 1)
assert.equal(field.buttons[0].dataset.segmentSelected, 'true')
assert.equal(field.buttons[0].tabIndex, 0)
assert.equal(field.component.$refs.track.classList.contains('is-hydrated'), true)
let hydrationWrites = 0
const originalAdd = field.component.$refs.track.classList.add
field.component.$refs.track.classList.add = (...names) => { hydrationWrites++; originalAdd(...names) }
field.component.layoutSegments(); field.component.layoutSegments()
assert.equal(hydrationWrites, 0, 'layout must not rewrite unchanged observed track classes and start a RAF feedback loop')
const mountedPill = field.component.$refs.indicator.style.cssText
field.component.$refs.track.offsetWidth = 0
field.buttons[0].offsetWidth = 0
field.component.layoutSegments()
assert.equal(field.component.$refs.indicator.style.cssText, mountedPill, 'Hidden ancestor must preserve mounted pill geometry')
assert.equal(field.component.$refs.track.classList.contains('is-hydrated'), true, 'Hidden tabs must not restart first hydration')
field.component.$refs.track.offsetWidth = 240
field.buttons[0].offsetWidth = 80
field.component.layoutSegments()
assert.equal(field.component.$refs.indicator.style.cssText, mountedPill, 'Returning to a mounted tab must preserve the selected pill')
field.component.select(1)
assert.equal(field.component.state, 0, 'disabled value must not be selectable')
let prevented = false
field.component.onSegmentKeydown({ target: field.buttons[0], key: 'ArrowRight', preventDefault() { prevented = true } })
assert(prevented)
assert.equal(field.component.state, '2')
assert(field.buttons[2].focused)
field.component.$refs.track.rtl = true
field.component.onSegmentKeydown({ target: field.buttons[2], key: 'ArrowRight', preventDefault() {} })
assert.equal(field.component.state, '0', 'RTL direction must be logical')
field.component.state = 'unknown'
field.watches.get('state')('unknown')
flush()
assert.equal(field.component.state, 'unknown', 'field adapter must not silently rewrite externally bound invalid values')
field.component.destroy(); field.component.destroy()
assert.equal(resizeDisposed, 1); assert.equal(mutationDisposed, 1)
assert.equal(frames.size, 0, 'owner frames must be cancelled')

const listeners = new Map()
const stored = new Map([['locale-tabs', JSON.stringify('pl')]])
globalThis.localStorage = { getItem: key => stored.get(key), setItem: (key, value) => stored.set(key, value) }
globalThis.window = { location: { search: '?tab=pl', href: 'https://local.test/?tab=pl' }, addEventListener: (key, fn) => listeners.set(key, fn), removeEventListener: key => listeners.delete(key) }
globalThis.history = { state: { retained: true }, replaceState(state, title, url) { this.lastState = state; this.lastUrl = String(url) } }
const locale = mount(tabs({ activeTabKey: 'en', optionKeys: ['en', 'pl'], isTabPersisted: true, tabPersistKey: 'locale-tabs', livewireId: 'host', schemaKey: 'form' }), [button('en'), button('pl')])
await locale.component.init()
assert.equal(locale.component.tab, 'pl')
locale.component.select('en'); locale.watches.get('tab')('en')
assert.equal(JSON.parse(stored.get('locale-tabs')), 'en')
locale.buttons[0].style.display = 'none'
locale.component.syncSegmentSelection()
assert.equal(locale.component.tab, 'pl', 'client-hidden selected tab must fall back to an available tab')
locale.component.destroy()
assert.equal(listeners.size, 0)
const query = mount(tabs({ activeTabKey: 'en', optionKeys: ['en', 'pl'], isTabPersistedInQueryString: true, tabQueryStringKey: 'tab' }), [button('en'), button('pl')])
await query.component.init()
assert.equal(query.component.tab, 'pl')
query.component.select('en'); query.watches.get('tab')('en')
assert.equal(history.lastState, history.state, 'query persistence must retain Livewire history state')
assert(history.lastUrl.endsWith('?tab=en'))
query.component.destroy()

const element = { ...button('pl'), matches: selector => selector === '[data-segment-value]', hasAttribute: name => name in element.attrs }
element.attrs['data-segment-value'] = 'pl'; element.attrs['data-segment-selected'] = 'true'; element.attrs['aria-selected'] = 'true'; element.attrs.tabindex = '0'
const incoming = { ...button('pl'), attrs: { 'data-segment-value': 'pl', 'data-segment-selected': 'false' }, matches: element.matches }
preserveSegmentPresentation(element, incoming)
assert.equal(incoming.attrs['data-segment-selected'], 'true')
assert.equal(incoming.attrs['aria-selected'], 'true')
const scroll = { rtl: true, clientWidth: 200, scrollWidth: 1000, scrollLeft: 0, scrollTo(options) { this.scrollLeft = options.left } }
scrollSegmentItemIntoView(scroll, { offsetLeft: 400, offsetWidth: 80 })
assert.equal(scroll.scrollLeft, -460, 'RTL overflow must use the browser negative scroll interval')
const inactiveScroll = { clientWidth: 0, dataset: {} }
assert.equal(positionSegmentOverflowScrollElement(inactiveScroll), false)
assert.equal(inactiveScroll.dataset.ssrScrollPositioned, undefined, 'inactive shells must not lock in zero-width SSR geometry')
console.log('PASS: shared segment runtime single-flight init/readiness, numeric/disabled choices, keyboard + RTL, externally bound state, locale/query persistence, client visibility repair, morph presentation and complete owner disposal.')

const mountedFactory = {
    dataset: { fffAlpineReady: 'true' }, _x_dataStack: [{}],
    hasAttribute: name => name === 'x-fff-load',
    getAttribute: name => name === 'x-data' ? 'segmentControlFormComponent({ state: 1 })' : '/segment-control.js?v=hash',
}
const refreshedFactory = { ...mountedFactory, dataset: {} }
preserveMountedFactoryReadiness(mountedFactory, refreshedFactory)
assert.equal(refreshedFactory.dataset.fffAlpineReady, 'true', 'a server refresh must not erase readiness of an initialized live factory')
const changedFactory = { ...refreshedFactory, dataset: {}, getAttribute: name => name === 'x-data' ? 'otherComponent()' : '/segment-control.js?v=hash' }
preserveMountedFactoryReadiness(mountedFactory, changedFactory)
assert.equal(changedFactory.dataset.fffAlpineReady, undefined, 'a different factory must earn its own readiness')
const failedFactory = { ...mountedFactory, _x_async: 'failed' }
const retryFactory = { ...refreshedFactory, dataset: {} }
preserveMountedFactoryReadiness(failedFactory, retryFactory)
assert.equal(retryFactory.dataset.fffAlpineReady, undefined, 'failed factories must retain the retry gate')
console.log('PASS: Livewire preserves actual initialized factory readiness, while changed or failed factories retain initialization gates.')
globalThis.CustomEvent ??= class { constructor(type, options) { this.type = type; Object.assign(this, options) } }
const readinessHost = { dataset: {}, isConnected: true, _x_dataStack: [{}], dispatchEvent() {} }
let ownerDisposed = 0
const readyState = withFactoryReadiness(() => ({ init() { return Promise.resolve() }, destroy() { ownerDisposed++ } }))()
readyState.$el = readinessHost
assert.equal(isMountedFactoryReady(readinessHost), false)
await readyState.init()
delete readinessHost.dataset.fffAlpineReady
assert.equal(isMountedFactoryReady(readinessHost), true, 'SSR attribute replacement must not erase actual factory readiness')
readyState.destroy()
assert.equal(ownerDisposed, 1)
assert.equal(isMountedFactoryReady(readinessHost), false, 'disposal must revoke actual owner readiness')
console.log('PASS: factory readiness survives SSR attribute replacement and is revoked on owner disposal.')
