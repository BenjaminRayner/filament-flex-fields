import assert from 'node:assert/strict'
import { createSegmentPanelPaint } from '../../resources/js/runtime/filament/segment-panel-paint.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createElement, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const body = createElement('body'); body.parentElement = {}
const document = Object.assign(new EventTarget(), { body, querySelectorAll: selector => body.querySelectorAll(selector) })
let disconnected = false
const window = { ResizeObserver: class { observe() {} unobserve() {} disconnect() { disconnected = true } } }
const lifetime = createResourceScope()
const missing = new Set()
function panel(parent, height) {
    const node = createElement('div')
    node.classList.add('fff-segment-tabs__panel', 'is-active')
    node.getBoundingClientRect = () => ({ height })
    node.style.setProperty = (key, value) => { node.style[key] = value }
    node.style.removeProperty = key => { delete node.style[key] }
    const query = node.querySelectorAll.bind(node)
    node.querySelectorAll = selector => query(selector).filter(child => child !== node)
    parent.appendChild(node)
    const batch = createAssetBatch([], [], {})
    batch.setAttribute('data-fff-assets-ready', '')
    node.appendChild(batch)
    return { node, batch }
}
const outer = panel(body, 500), inner = panel(outer.node, 320), sibling = panel(body, 240)
const paint = createSegmentPanelPaint({ document, window, lifetime, needsLoading: node => missing.has(node) })
missing.add(inner.node); missing.add(outer.node); missing.add(sibling.node)
inner.batch.removeAttribute('data-fff-assets-ready'); sibling.batch.removeAttribute('data-fff-assets-ready')
const release = paint.prepare(body)
assert(inner.node.hasAttribute('data-fff-panel-preparing'))
assert(!outer.node.hasAttribute('data-fff-panel-preparing'), 'Only the active leaf must be gated; its tab strip stays visible')
assert.equal(inner.node.style['--fff-panel-ready-height'], '320px')
assert.equal(release(), false, 'Unready requirements must never release the cached footprint')
missing.delete(inner.node); inner.batch.setAttribute('data-fff-assets-ready', '')
assert.equal(release(), false, 'One ready panel must not reveal its unready sibling')
assert(!inner.node.hasAttribute('data-fff-panel-preparing'))
assert(sibling.node.hasAttribute('data-fff-panel-preparing'))
missing.delete(sibling.node)
assert.equal(release(), false, 'A loaded stylesheet still needs its consumer paint readiness')
sibling.batch.setAttribute('data-fff-assets-ready', '')
assert.equal(release(), true)
inner.node.getBoundingClientRect = () => ({ height: 360 })
const trigger = createElement('button')
trigger.closest = () => outer.node
const beforeChange = new Event('fff-segment-before-change')
Object.defineProperty(beforeChange, 'target', { value: trigger })
document.dispatchEvent(beforeChange)
inner.batch.removeAttribute('data-fff-assets-ready')
const cachedRelease = paint.prepare(inner.node)
assert(inner.node.hasAttribute('data-fff-panel-preparing'), 'Cached shared transport must still gate revoked consumer readiness as one panel')
assert.equal(inner.node.style['--fff-panel-ready-height'], '360px', 'Trigger events capture the owning panels before they hide')
assert.equal(cachedRelease(), false)
inner.batch.setAttribute('data-fff-assets-ready', '')
assert.equal(cachedRelease(), true)
sibling.node.classList.add('fff-flex-fields-assets-pending')
missing.add(sibling.node)
paint.prepare(sibling.node)
assert(!sibling.node.hasAttribute('data-fff-panel-preparing'), 'Native first-load barrier must never acquire a second presentation gate')
sibling.node.classList.remove('fff-flex-fields-assets-pending')
missing.delete(sibling.node)
missing.add(inner.node)
paint.prepare(outer.node)
lifetime.dispose()
assert(disconnected)
assert(!inner.node.hasAttribute('data-fff-panel-preparing'), 'Disposal must remove pending presentation')
assert.equal(inner.node.style['--fff-panel-ready-height'], undefined)
console.log('PASS: returning panel retains styled geometry, gates only active leaves, releases siblings independently after CSS readiness, and disposes observation/presentation.')
