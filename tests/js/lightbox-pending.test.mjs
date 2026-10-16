import test from 'node:test'
import assert from 'node:assert/strict'
import { createPendingSurface } from '../../resources/js/runtime/filament/pending-surface.js'
import { createElement } from './helpers/flex-field-asset-injector-dom.mjs'

test('an explicitly configured lightbox waits for readiness without form skeleton bones', async () => {
    const target = createElement('div'), window = createElement('div')
    target.classList.add('fi-modal'); window.classList.add('fi-modal-window')
    window.setAttribute('data-fff-loading-presentation', 'lightbox'); target.appendChild(window)
    const pending = createPendingSurface({ document: { createElement }, window: globalThis, resolvePendingTarget: () => target,
        injectorHooks: { markPendingStarted() {}, getPendingReleaseDelayMs: () => 0, markReady() {} }, awaitInflightAssetLoads: async () => {} })
    pending.applyPendingState(target)
    assert.equal(target.classList.contains('fff-flex-fields-assets-pending'), true)
    const overlay = window.children.at(-1)
    assert.equal(overlay.classList.contains('fff-flex-fields-assets-skeleton--lightbox'), true)
    assert.equal(overlay.children.length, 0)
    await pending.releasePendingState(target, { force: true })
    assert.equal(target.classList.contains('fff-flex-fields-assets-pending'), false)
    pending.dispose()
})
