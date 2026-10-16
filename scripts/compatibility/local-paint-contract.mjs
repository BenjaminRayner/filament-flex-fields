import assert from 'node:assert/strict'
import { createSegmentPanelPaint } from '../../resources/js/runtime/filament/segment-panel-paint.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { awaitSurfaceFactories } from '../../resources/js/runtime/filament/surface-readiness.js'
import { createElement, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const body = createElement('body'); body.parentElement = {}
const document = Object.assign(new EventTarget(), { body, querySelectorAll: s => body.querySelectorAll(s) })
const window = Object.assign(new EventTarget(), { setTimeout, clearTimeout,
    ResizeObserver: class { observe() {} unobserve() {} disconnect() {} },
    MutationObserver: class { observe() {} disconnect() {} } })
const panel = createElement('div'); panel.classList.add('fff-segment-tabs__panel', 'is-active')
panel.getBoundingClientRect = () => ({ height: 500 })
panel.style.setProperty = (key, value) => { panel.style[key] = value }
panel.style.removeProperty = key => { delete panel.style[key] }
const query = panel.querySelectorAll.bind(panel)
panel.querySelectorAll = s => query(s).filter(child => child !== panel)
body.appendChild(panel)
const own = createAssetBatch([], [], {}); own.setAttribute('data-fff-assets-ready', ''); panel.appendChild(own)
const card = createElement('div'); card.setAttribute('data-fff-local-paint', ''); panel.appendChild(card)
const cardMatches = card.matches.bind(card)
card.matches = selector => selector === '[data-fff-local-paint]' || cardMatches(selector)
const child = createAssetBatch([], [], {}); child.setAttribute('data-fff-assets-ready', 'failed'); card.appendChild(child)
const field = createElement('div'); field.setAttribute('x-fff-load', ''); field._x_async = 'failed'; card.appendChild(field)
const fieldMatches = field.matches.bind(field)
field.matches = selector => selector === '[x-fff-load]' || fieldMatches(selector)
const events = new EventTarget()
panel.addEventListener = events.addEventListener.bind(events)
panel.removeEventListener = events.removeEventListener.bind(events)
const lifetime = createResourceScope()
const paint = createSegmentPanelPaint({ document, window, lifetime, needsLoading: root => root === child })
paint.prepare(panel)
assert(!panel.hasAttribute('data-fff-panel-preparing'), 'A self-gated editor failure cannot hide its parent tab')
own.removeAttribute('data-fff-assets-ready')
const release = paint.prepare(panel)
assert(panel.hasAttribute('data-fff-panel-preparing'), 'Ordinary parent CSS still requires a paint gate')
own.setAttribute('data-fff-assets-ready', '')
assert.equal(release(), true)
await awaitSurfaceFactories(panel, { document, window, excludeLocalPaint: true })
await assert.rejects(awaitSurfaceFactories(panel, { document, window }), /initialization failed/,
    'Modal readiness must still include locally gated descendants')
lifetime.dispose()
console.log('PASS: local failures are isolated, parent CSS remains gated, and modal readiness includes all active children.')
