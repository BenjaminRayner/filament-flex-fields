import assert from 'node:assert/strict'
import test from 'node:test'

import {
    connectionSaveDataEnabled,
    createFlexFieldAssetInjector,
    normalizeAssetUrl,
    prefersReducedMotion,
    shouldDeferBackgroundAssetPreload,
} from '../../resources/js/core/flex-field-asset-injector.js'

import { createClassList, createLink, createElement, createAssetBatch, createDom, flushStylesheetLoads } from './helpers/flex-field-asset-injector-dom.mjs'

test('normalizeAssetUrl resolves relative and absolute urls to the same href', () => {
    const baseUri = 'https://panel.test/admin/forms'

    assert.equal(
        normalizeAssetUrl('/css/janczakb/filament-flex-fields/flex-fields-phone-field.css', baseUri),
        'https://panel.test/css/janczakb/filament-flex-fields/flex-fields-phone-field.css',
    )

    assert.equal(
        normalizeAssetUrl('https://panel.test/css/janczakb/filament-flex-fields/flex-fields-phone-field.css', baseUri),
        'https://panel.test/css/janczakb/filament-flex-fields/flex-fields-phone-field.css',
    )
})

test('normalizeAssetUrl retains content version identity', () => {
    const baseUri = 'https://panel.test/admin'

    const a = normalizeAssetUrl('/js/janczakb/filament-flex-fields/components/select-field.js?v=111', baseUri)
    const b = normalizeAssetUrl('/js/janczakb/filament-flex-fields/components/select-field.js?v=222', baseUri)

    assert.notEqual(a, b)
    assert.equal(a, 'https://panel.test/js/janczakb/filament-flex-fields/components/select-field.js?v=111')
})

test('resolveHoverPreloadScope prefers the nearest field wrapper over the document', () => {
    const { document, window } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })

    const form = createElement('form')
    const field = createElement('div')
    field.classList.add('fi-fo-field-wrp')

    const batch = createElement('span')
    batch.attributes = { 'data-fff-asset-batch': '' }
    field.children.push(batch)
    form.children.push(field)

    const button = createElement('button')
    field.children.push(button)
    button.parentElement = field
    button.closest = (selector) => {
        if (selector === '[data-fff-asset-batch]') {
            return batch
        }

        if (selector === '.fi-fo-field-wrp') {
            return field
        }

        if (selector === 'form') {
            return form
        }

        return null
    }

    assert.equal(injector.resolveHoverPreloadScope(button), field)
})

test('loadStylesheet deduplicates concurrent requests through the inflight cache', async () => {
    const { document, window, head } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })
    const href = '/css/janczakb/filament-flex-fields/flex-fields-phone-field.css'

    const first = injector.loadStylesheet(href)
    const second = injector.loadStylesheet(href)

    assert.equal(first, second)

    await flushStylesheetLoads(head)
    const created = head.children.find((child) => child.rel === 'stylesheet')
    assert.ok(created)
    created.dispatchEvent('load')

    await first

    assert.equal(head.children.filter((child) => child.rel === 'stylesheet').length, 1)
    assert.equal(injector.isStylesheetLoaded(href), true)
})

test('resolvePendingTarget prefers the closest Filament modal container', () => {
    const { document, window } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })

    const modal = createElement('div')
    modal.classList.add('fi-modal')

    const form = createElement('div')
    form.parentElement = modal
    modal.children.push(form)

    assert.equal(injector.resolvePendingTarget(form), modal)
})

test('batchNeedsLoading returns true when alpine chunks are missing', () => {
    const { document, window } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })

    const batch = createElement('span')
    batch.attributes = {
        'data-fff-stylesheets': '[]',
        'data-fff-chunks': '["/js/janczakb/filament-flex-fields/components/select-field.js"]',
    }

    assert.equal(injector.batchNeedsLoading(batch), true)
})





test('preloadBatchesIn starts downloads without removing batch markers', async () => {
    const { document, window, head } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })

    const batch = createElement('span')
    batch.attributes = {
        'data-fff-asset-batch': '',
        'data-fff-stylesheets': '["/css/janczakb/filament-flex-fields/flex-fields-phone-field.css"]',
        'data-fff-chunks': '[]',
    }

    const root = createElement('div')
    document.body.appendChild(root)
    root.appendChild(batch)
    root.querySelectorAll = (selector) => (selector === '[data-fff-asset-batch]' ? [batch] : [])

    batch.setAttribute('data-fff-asset-sizes', JSON.stringify({ [JSON.parse(batch.getAttribute('data-fff-stylesheets'))[0]]: 1024 }))
    const pending = injector.preloadBatchesIn(root)

    await flushStylesheetLoads(head)
    const created = head.children.find((child) => child.rel === 'stylesheet')
    assert.ok(created)
    created.dispatchEvent('load')

    await pending

    assert.equal(batch.hasAttribute('data-fff-asset-batch'), true)
    assert.equal(injector.rootNeedsAssetLoading(root), false)
})




test('protected stylesheet links are never removed during dedupe', async () => {
    const { document, window, head } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })
    const href = 'https://panel.test/css/janczakb/filament-flex-fields/flex-fields-phone-field.css'

    const protectedLink = createLink({
        href,
        attributes: {
            'data-fff-stylesheet': 'phone-field',
        },
    })

    head.appendChild(protectedLink)
    protectedLink.dispatchEvent('load')

    await injector.ensureAssets(document)

    assert.equal(head.children.includes(protectedLink), true)
})

test('loadStylesheet resolves immediately when an existing inline link is already loaded', async () => {
    const { document, window, body } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })
    const href = '/css/janczakb/filament-flex-fields/flex-fields-phone-field.css'

    const existing = createLink({
        href,
        attributes: {
            'data-fff-stylesheet': 'phone-field',
        },
    })
    existing.sheet = {}
    body.appendChild(existing)

    await injector.loadStylesheet(href)

    assert.equal(injector.isStylesheetLoaded(href), true)
    assert.equal(body.children.filter((child) => child.rel === 'stylesheet').length, 1)
})





test('isStylesheetLoaded forgets stale cache after Livewire removes the head link (resource tabs)', async () => {
    const { document, window, head } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })
    const href = '/css/janczakb/filament-flex-fields/flex-fields-item-card.css'

    const firstLoad = injector.loadStylesheet(href)
    await flushStylesheetLoads(head)
    const first = head.children.find((child) => child.rel === 'stylesheet')
    assert.ok(first)
    first.dispatchEvent('load')
    await firstLoad

    assert.equal(injector.isStylesheetLoaded(href), true)

    // Simulate wire:navigate / Filament resource tab: Livewire drops navigate-tracked CSS.
    first.remove()

    assert.equal(injector.isStylesheetLoaded(href), false)

    const secondLoad = injector.loadStylesheet(href)
    await flushStylesheetLoads(head)
    const second = head.children.find((child) => child.rel === 'stylesheet')
    assert.ok(second)
    second.dispatchEvent('load')
    await secondLoad

    assert.equal(injector.isStylesheetLoaded(href), true)
})











test('reduced motion preserves readiness and saveData disables speculation', async () => {
    const { document, window, head } = createDom()
    const injector = createFlexFieldAssetInjector({ document, window })

    window.matchMedia = query => ({ matches: query === '(prefers-reduced-motion: reduce)' })
    assert.equal(prefersReducedMotion(window), true)
    assert.equal(shouldDeferBackgroundAssetPreload(window), false)

    const batch = createElement('span')
    batch.attributes = {
        'data-fff-asset-batch': '',
        'data-fff-stylesheets': '["/css/janczakb/filament-flex-fields/flex-fields-phone-field.css"]',
        'data-fff-chunks': '[]',
    }
    batch.getBoundingClientRect = () => ({
        top: 0,
        left: 0,
        bottom: 100,
        right: 100,
    })

    document.querySelectorAll = (selector) => (
        selector === '[data-fff-asset-batch]' ? [batch] : []
    )

    await injector.preloadVisibleBatchesIn(document)

    assert.equal(head.children.length, 0)

    window.matchMedia = () => ({ matches: false })
    window.navigator = { connection: { saveData: true } }

    assert.equal(connectionSaveDataEnabled(window), true)
    assert.equal(shouldDeferBackgroundAssetPreload(window), true)

    await injector.preloadVisibleBatchesIn(document)

    assert.equal(head.children.length, 0)
})
