import test from 'node:test'
import assert from 'node:assert/strict'
import { createSegmentPanelPaint } from '../../resources/js/runtime/filament/segment-panel-paint.js'

test('initially styled tabs never hide all content while their first factories are still initializing', () => {
    const attrs = new Map(), field = { isConnected: true, closest: () => null, _fffFactoryReady: false }
    const batch = { isConnected: true, closest: () => null, getAttribute: name => name === 'data-fff-assets-ready' ? '' : null }
    const panel = { isConnected: true, classList: { contains: c => c === 'fff-segment-tabs__panel' || c === 'is-active' },
        querySelector: () => null, querySelectorAll: selector => selector === '[x-fff-load]' ? [field] : selector.includes('data-fff-asset-batch') ? [batch] : [],
        matches: selector => selector === '.fff-segment-tabs__panel', getBoundingClientRect: () => ({ height: 300 }),
        setAttribute: (k,v) => attrs.set(k,v), removeAttribute: k => attrs.delete(k), getAttribute: k => attrs.get(k) ?? null,
        style: { setProperty() {}, removeProperty() {} },
    }
    const document = { body: {}, querySelectorAll: () => [panel] }, disposals = []
    let resize
    const window = { ResizeObserver: class { constructor(fn) { resize = fn } observe() {} unobserve() {} disconnect() {} } }
    const paint = createSegmentPanelPaint({ document, window, lifetime: { listen() {}, defer: fn => disposals.push(fn) }, needsLoading: () => false })
    paint.prepare(panel)
    assert.equal(attrs.has('data-fff-panel-preparing'), false)
    field._fffFactoryReady = true
    resize([{ target: panel }])
    // A later reactivation really does wait for this instance's factory readiness.
    field._fffFactoryReady = false
    const release = paint.prepare(panel)
    assert.equal(attrs.has('data-fff-panel-preparing'), true)
    field._fffFactoryReady = true
    release()
    assert.equal(attrs.has('data-fff-panel-preparing'), false)
    disposals.forEach(fn => fn())
})
