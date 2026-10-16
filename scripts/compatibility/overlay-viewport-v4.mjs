import assert from 'node:assert/strict'
import { resolveOverlayViewport, resolveOverlayBaseLayer } from '../../resources/js/overlay/geometry/viewport.js'
import { resolveTeleportedMenuVerticalPlacement } from '../../resources/js/core/teleported-menu-position.js'
import { findContainedId } from '../../resources/js/runtime/dom/find-contained-id.js'
import { resolveOwnedOverlayStack } from '../../resources/js/overlay/ownership/stacking.js'

const header = { getBoundingClientRect: () => ({ top: 0, bottom: 64, height: 64 }) }
const view = { innerHeight: 720, getComputedStyle: () => ({ position: 'sticky' }) }
const doc = { defaultView: view, querySelectorAll: selector => {
    assert.ok(selector.includes('.fi-topbar-ctn'))
    return [header]
} }
const anchor = { ownerDocument: doc, closest: () => null }
const bounds = resolveOverlayViewport(anchor)
assert.deepEqual(bounds, { top: 80, bottom: 704, height: 624 })
const placement = resolveTeleportedMenuVerticalPlacement({ triggerRect: { top: 170, bottom: 210 }, panelHeight: 624, windowHeight: 720, viewportTop: bounds.top, viewportBottom: bounds.bottom })
assert.equal(placement.top, 80)
assert.ok(placement.top + 624 <= bounds.bottom)
const modal = { ...anchor, closest: selector => {
    assert.ok(selector.includes('aria-modal="true"'))
    return {}
} }
assert.equal(resolveOverlayViewport(modal).top, 16)
assert.equal(resolveOverlayBaseLayer(modal), 60)
assert.equal(resolveOverlayBaseLayer(anchor), 20)
const top = resolveTeleportedMenuVerticalPlacement({ triggerRect: { top: 90, bottom: 130 }, panelHeight: 260, forcedPlacement: 'top', windowHeight: 720, viewportTop: 80 })
assert.equal(top.top, 80)
assert.equal(top.opensAbove, true)
console.log('PASS: sticky wrapper bounds, oversized panel clamping, forced placement, and genuine modal layer isolation.')
const option = {}
const scopedRoot = { ownerDocument: { getElementById: id => id === 'mountedActions.0.data.social__platform-option-instagram' ? option : null }, contains: el => el === option }
assert.equal(findContainedId(scopedRoot, 'mountedActions.0.data.social__platform-option-instagram'), option)
assert.equal(findContainedId({ ...scopedRoot, contains: () => false }, 'mountedActions.0.data.social__platform-option-instagram'), null)
assert.equal(findContainedId(null, 'absent'), null)
console.log('PASS: literal modal IDs and root containment, without interpreting state paths as selectors.')
for (const [modalContext, expected] of [[false, 22], [true, 62]]) {
    const owner = { dataset: { fffDropdownOwner: 'inline-color' } }
    const nestedAnchor = { ownerDocument: { defaultView: { getComputedStyle: () => ({ zIndex: 'auto' }) } },
        closest: selector => selector === '[data-fff-dropdown-owner]' ? owner : (modalContext ? {} : null) }
    const variables = new Map()
    const stack = resolveOwnedOverlayStack({ style: { setProperty: (key, value) => variables.set(key, value) } }, nestedAnchor, modalContext ? 60 : 20)
    assert.equal(stack.panel, expected)
    assert.equal(stack.nested, true)
    assert.equal(variables.get('--fff-nested-overlay-z'), String(expected))
}
console.log('PASS: z-index:auto inline owners resolve explicit page/modal child layers.')
