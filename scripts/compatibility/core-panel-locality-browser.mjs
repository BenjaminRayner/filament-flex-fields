import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const output = await build({ stdin: { resolveDir: root, contents: `
import { createFlexFieldAssetInjector } from './resources/js/runtime/asset-runtime.js'
import { createSegmentPanelPaint } from './resources/js/runtime/filament/segment-panel-paint.js'
import { createResourceScope } from './resources/js/runtime/lifecycle/resource-scope.js'
import { awaitSurfaceFactories } from './resources/js/runtime/filament/surface-readiness.js'
import { createPendingSurface } from './resources/js/runtime/filament/pending-surface.js'
import { createSurfaceRegistry } from './resources/js/runtime/filament/surface-registry.js'
import { createSurfaceReadinessIndex } from './resources/js/runtime/filament/surface-readiness-index.js'
window.fixture = { runtime: createFlexFieldAssetInjector, paint: createSegmentPanelPaint, scope: createResourceScope, wait: awaitSurfaceFactories, pending: createPendingSurface, index: createSurfaceReadinessIndex, surfaces: createSurfaceRegistry }
window.frames = async () => { await new Promise(requestAnimationFrame); await new Promise(requestAnimationFrame); await new Promise(requestAnimationFrame) }
window.makeField = parent => {
 const field = document.createElement('div'); field.setAttribute('x-fff-load', ''); field._fffFactoryReady = true; field.style.height = '1px'
 const batch = document.createElement('span'); batch.hidden = true; batch.dataset.fffAssetBatch = ''; batch.dataset.fffAssetConsumer = 'probe'; batch.dataset.fffStylesheets = '[]'; batch.dataset.fffChunks = '[]'; batch.dataset.fffAssetsReady = ''
 field.append(batch); parent.append(field); return { field, batch }
}
window.makePanel = () => { const panel = document.createElement('section'); panel.className = 'fff-segment-tabs__panel is-active'; panel.setAttribute('role', 'tabpanel'); document.body.append(panel); return panel }
` }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const server = createServer((request, response) => {
    response.writeHead(200, { 'Content-Type': request.url === '/probe.js' ? 'text/javascript' : 'text/html', 'Cache-Control': 'no-store' })
    response.end(request.url === '/probe.js' ? output.outputFiles[0].contents : '<!doctype html><body><script type="module" src="/probe.js"></script>')
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true }); const page = await browser.newPage(), errors = [], proofs = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' }); await page.waitForFunction(() => window.fixture)
    const measurements = await page.evaluate(async () => {
        const result = []
        for (const count of [100, 1000, 5000]) {
            const panel = makePanel(), fields = Array.from({ length: count }, () => makeField(panel))
            const runtime = fixture.runtime({ document, window }); window.FffAssetInjector = runtime; runtime.boot()
            await runtime.ensureAssets(panel); await frames()
            let unrelatedReads = 0, broadQueries = 0
            for (const { field } of fields.slice(1)) Object.defineProperty(field, '_fffFactoryReady', { get() { unrelatedReads++; return true }, configurable: true })
            const query = panel.querySelectorAll.bind(panel)
            panel.querySelectorAll = selector => { broadQueries++; return query(selector) }
            for (let i = 0; i < 100; i++) { fields[0].batch.removeAttribute('data-fff-assets-ready'); await frames() }
            if (unrelatedReads || broadQueries) throw Error('Local panel change inspected siblings: ' + JSON.stringify({ unrelatedReads, broadQueries }))
            if (panel.hasAttribute('data-fff-panel-preparing') || runtime.getDiagnostics().records.length) throw Error('Panel failed to settle')
            result.push({ consumers: count, localUpdates: 100, unrelatedFactoryReads: unrelatedReads, panelWideQueries: broadQueries })
            runtime.dispose(); delete window.FffAssetInjector; panel.remove()
        }
        return result
    })
    proofs.push('full minified runtime at 100/1000/5000 panel fields: local readiness revocation/restoration makes zero sibling readiness reads and zero panel-wide queries')
    await page.evaluate(async () => {
        const panel = makePanel(), a = makeField(panel), b = makeField(panel)
        const life = fixture.scope(), paint = fixture.paint({ document, window, lifetime: life, needsLoading: () => false })
        await frames()
        a.field._fffFactoryReady = false; b.field._fffFactoryReady = false
        paint.prepare(a.field); paint.prepare(b.field)
        if (!panel.hasAttribute('data-fff-panel-preparing')) throw Error('Retained panel not masked')
        const first = paint.settle(a.field), second = paint.settle(b.field)
        a.field._fffFactoryReady = true; a.field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true })); paint.update(a.field)
        paint.release(a.field); if (!panel.hasAttribute('data-fff-panel-preparing')) throw Error('First sibling revealed second pending init')
        b.field._fffFactoryReady = true; b.field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true })); paint.update(b.field)
        await Promise.all([first, second]); paint.release(b.field)
        if (panel.hasAttribute('data-fff-panel-preparing')) throw Error('Ready siblings not released')
        life.dispose(); panel.remove()
    })
    proofs.push('two concurrent child initializations retain one panel paint barrier; the first completion cannot reveal the second pending child')
    await page.evaluate(async () => {
        const panel = makePanel(), a = makeField(panel), life = fixture.scope(), paint = fixture.paint({ document, window, lifetime: life, needsLoading: () => false })
        await frames(); a.field._fffFactoryReady = false; a.field._x_async = 'failed'; paint.prepare(a.field)
        await paint.settle(a.field).then(() => { throw Error('Failed init accepted') }, () => {})
        paint.fail(a.field, () => {}); if (!panel.querySelector('[data-fff-panel-retry]')) throw Error('Retry UI missing')
        a.field._x_async = 'complete'; a.field._fffFactoryReady = true; paint.update(a.field)
        await paint.settle(a.field); paint.release(a.field)
        if (panel.hasAttribute('data-fff-panel-preparing') || panel.querySelector('[data-fff-panel-retry]')) throw Error('Successful retry not released')
        const local = document.createElement('div'); local.dataset.fffLocalPaint = ''; panel.append(local); const child = makeField(local); child.field._fffFactoryReady = false
        paint.prepare(local); if (panel.hasAttribute('data-fff-panel-preparing')) throw Error('Self-gated child blocked parent')
        life.dispose(); panel.remove()
    })
    proofs.push('failed initialization retains retry UI; successful retry releases it; independently gated local child never hides a ready parent')
    await page.evaluate(async () => {
        const host = document.createElement('section'); document.body.append(host)
        const fields = Array.from({ length: 1000 }, () => { const value = makeField(host); value.field._fffFactoryReady = false; return value.field })
        const controller = new AbortController(), wait = fixture.wait(host, { document, window, signal: controller.signal })
        let unrelatedReads = 0
        for (const field of fields.slice(1)) Object.defineProperty(field, '_fffFactoryReady', { get() { unrelatedReads++; return false }, configurable: true })
        fields[0]._fffFactoryReady = true; fields[0].dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true })); await Promise.resolve()
        if (unrelatedReads) throw Error('One init event rescanned all waiting factories')
        controller.abort(); await wait
        host.remove()
    })
    proofs.push('surface waiting at 1000 factories processes one init event without reading unrelated factories; abort releases observation and deadline')
    await page.evaluate(async () => {
        const host = document.createElement('section'); document.body.append(host)
        const a = makeField(host); a.field._fffFactoryReady = false
        const controller = new AbortController(), wait = fixture.wait(host, { document, window, signal: controller.signal })
        const b = makeField(host); b.field._fffFactoryReady = false
        await Promise.resolve(); a.field.remove(); await Promise.resolve()
        let settled = false; wait.then(() => { settled = true }); await Promise.resolve()
        if (settled) throw Error('Inserted pending factory ignored after removal')
        b.field._fffFactoryReady = true; b.field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true })); await wait
        controller.abort(); host.remove()
        const first = makePanel(), second = makePanel(), value = makeField(first), index = fixture.index({ document, needsLoading: () => false })
        value.field._fffFactoryReady = false; index.refresh(first); index.refresh(second)
        if (index.status(first).factories !== 1) throw Error('Initial index missing')
        second.append(value.field); index.refresh(value.field)
        if (index.status(first).factories || index.status(second).factories !== 1) throw Error('Moved contribution duplicated or lost')
        value.field.remove(); index.forget(value.field)
        if (index.status(second).factories || index.status(second).batches) throw Error('Detached contribution retained')
        index.clear(); first.remove(); second.remove()
    })
    proofs.push('waiting surfaces discover insertion/removal deltas; connected moves transfer panel readiness contributions once; genuine detach removes them')
    await page.evaluate(async () => {
        const host = document.createElement('section'); document.body.append(host)
        const pending = fixture.pending({ document, window, resolvePendingTarget: el => el.closest('.fi-modal') ?? el, awaitInflightAssetLoads: async () => {}, injectorHooks: { markPendingStarted() {}, getPendingReleaseDelayMs: () => 0 } })
        const targets = Array.from({ length: 1000 }, () => { const node = document.createElement('div'); host.append(node); pending.applyPendingState(node); return node })
        let unrelatedReads = 0
        for (const target of targets.slice(1)) Object.defineProperty(target, 'isConnected', { get() { unrelatedReads++; return true }, configurable: true })
        if (pending.getPendingTargets(targets[0]).length !== 1 || unrelatedReads) throw Error('Local pending lookup inspected unrelated targets')
        const modal = document.createElement('div'); modal.className = 'fi-modal'; host.append(modal)
        const a = document.createElement('div'), b = document.createElement('div'); modal.append(a, b)
        pending.applyPendingState(a); pending.applyPendingState(b); await pending.releasePendingState(modal)
        if (pending.hasPendingState(a) || pending.hasPendingState(b) || pending.getPendingTargets(modal).length) throw Error('Modal descendant interests leaked after target release')
        const other = document.createElement('div'); other.className = 'fi-modal'; host.append(other)
        pending.applyPendingState(a); other.append(a)
        if (pending.getPendingTargets(a)[0] !== modal) throw Error('Connected move lost the captured pending surface')
        await pending.releasePendingState(a)
        if (pending.getPendingTargets(modal).length || pending.hasPendingState(a)) throw Error('Moved pending interest stranded its original surface')
        pending.dispose(); host.remove()
    })
    proofs.push('1000 pending surfaces use local ancestor lookup; releasing a shared modal clears all descendant interests; a connected move releases its captured surface')
    await page.evaluate(async () => {
        const runtime = fixture.runtime({ document, window }); window.FffAssetInjector = runtime
        const pageHost = document.createElement('section'); document.body.append(pageHost)
        const fields = Array.from({ length: 1000 }, () => makeField(pageHost))
        const parent = document.createElement('section'), child = document.createElement('section')
        parent.id = 'parent-modal'; child.id = 'child-modal'; parent.className = 'fi-modal fi-modal-open'; child.className = 'fi-modal fi-modal-open'
        document.body.append(parent, child); makeField(parent); makeField(child)
        await runtime.prepareModal({ detail: { id: parent.id } }); await runtime.prepareModal({ detail: { id: child.id } }); await runtime.ensureAssets(pageHost)
        const pageIDs = fields.map(({ batch }) => runtime.getConsumerGraph().resolveConsumerInstanceId(batch))
        let unrelatedReads = 0
        for (const { batch } of fields) Object.defineProperty(batch, 'isConnected', { get() { unrelatedReads++; return true }, configurable: true })
        parent.classList.remove('fi-modal-open'); child.classList.remove('fi-modal-open')
        await runtime.cleanupClosedModalPendingState({ detail: {} })
        if (unrelatedReads || runtime.getModalOpenStack().length !== 1 || runtime.getModalOpenStack()[0] !== 'modal:parent-modal') throw Error('Modal close inspected page owners or lost suspended parent')
        const current = new Set(runtime.getConsumerGraph().getActiveConsumerIds())
        if (pageIDs.some(id => !current.has(id))) throw Error('Modal close retired page consumers')
        runtime.dispose(); pageHost.remove(); parent.remove(); child.remove(); delete window.FffAssetInjector
    })
    proofs.push('closing the indexed top child modal inspects zero unrelated page consumers and retains its suspended parent and page claims')
    await page.evaluate(async () => {
        const panel = makePanel(), parent = makeField(panel)
        const children = Array.from({ length: 1000 }, () => makeField(parent.field))
        const runtime = fixture.runtime({ document, window }); window.FffAssetInjector = runtime; runtime.boot()
        await runtime.ensureAssets(panel); await frames()
        let siblingReads = 0, planReads = 0
        for (const { field, batch } of children) {
            Object.defineProperty(field, '_fffFactoryReady', { get() { siblingReads++; return true }, configurable: true })
            const get = batch.getAttribute.bind(batch)
            batch.getAttribute = name => { if (name === 'data-fff-stylesheets') planReads++; return get(name) }
        }
        parent.field.setAttribute('data-fff-alpine-ready', '')
        await frames()
        panel.style.transform = 'translateX(1px)'; panel.classList.add('cosmetic-theme')
        await frames()
        if (siblingReads || planReads) throw Error('Presentation change replayed nested factories or plans: ' + JSON.stringify({ siblingReads, planReads }))
        runtime.dispose(); panel.remove(); delete window.FffAssetInjector
    })
    proofs.push('factory completion with 1000 nested ready factories skips nested plan/factory replay; cosmetic transforms/theme classes do not trigger readiness work')
    await page.evaluate(async () => {
        const panel = makePanel(), a = makeField(panel)
        const runtime = fixture.runtime({ document, window }); window.FffAssetInjector = runtime; runtime.boot()
        await runtime.ensureAssets(panel); await frames()
        const field = document.createElement('div'); field.setAttribute('x-fff-load', ''); field._fffFactoryReady = false; panel.append(field)
        await frames()
        if (!panel.hasAttribute('data-fff-panel-preparing')) throw Error('Factory-only insertion did not retain the panel')
        field.remove(); await frames()
        if (panel.hasAttribute('data-fff-panel-preparing')) throw Error('Detached last waiting factory stranded panel')
        runtime.dispose(); panel.remove(); delete window.FffAssetInjector
    })
    proofs.push('factory-only insertion retains a ready panel; removing its last waiting child settles the cached barrier without old DOM ancestry')
    await page.evaluate(async () => {
        const host = document.createElement('section'); document.body.append(host); const field = makeField(host); field.field._fffFactoryReady = false
        const controller = new AbortController(), wait = fixture.wait(host, { document, window, signal: controller.signal })
        const registry = fixture.surfaces({ document }), oldModal = document.createElement('section'); oldModal.id = 'obsolete-modal'; oldModal.className = 'fi-modal'; host.append(oldModal)
        const oldKey = registry.resolveModalOwnerKey(oldModal)
        const oldBody = document.body, newBody = document.createElement('body'); oldBody.replaceWith(newBody)
        document.dispatchEvent(new Event('livewire:navigated'))
        await wait; controller.abort()
        registry.pruneDetachedModals()
        if (registry.getModalRoot(oldKey)) throw Error('Navigation retained a closed modal from the detached body')
        registry.dispose()
        newBody.replaceWith(oldBody); host.remove()
        const panel = makePanel(), a = makeField(panel), life = fixture.scope(), paint = fixture.paint({ document, window, lifetime: life, needsLoading: () => false })
        await frames(); a.field._fffFactoryReady = false; paint.prepare(a.field)
        const pending = paint.settle(a.field); life.dispose(); await pending
        a.field._fffFactoryReady = true; a.field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true })); await frames()
        if (panel.hasAttribute('data-fff-panel-preparing')) throw Error('Disposed paint mutated the panel after completion')
        panel.remove()
    })
    proofs.push('native body replacement ends old surface waiting; paint disposal aborts pending initialization and suppresses late presentation')
    await page.evaluate(async () => {
        const modal = document.createElement('section'); modal.id = 'local-morph'; modal.className = 'fi-modal fi-modal-open'; document.body.append(modal)
        const fields = Array.from({ length: 1000 }, () => makeField(modal))
        const runtime = fixture.runtime({ document, window }); window.FffAssetInjector = runtime; runtime.boot()
        await runtime.prepareModal({ detail: { id: modal.id } }); await frames()
        let unrelatedReads = 0, modalQueries = 0
        for (const { field } of fields.slice(1)) Object.defineProperty(field, '_fffFactoryReady', { get() { unrelatedReads++; return true }, configurable: true })
        const query = modal.querySelectorAll.bind(modal)
        modal.querySelectorAll = selector => { if (selector.includes('x-fff-load') || selector.includes('data-fff-asset-batch')) modalQueries++; return query(selector) }
        runtime.applyPendingState(fields[0].field)
        await runtime.handleMorphUpdated({ el: fields[0].field }); await frames()
        if (unrelatedReads || modalQueries || runtime.hasPendingState(modal)) throw Error('Local morph replayed modal siblings: ' + JSON.stringify({ unrelatedReads, modalQueries }))
        runtime.dispose(); modal.remove(); delete window.FffAssetInjector
    })
    proofs.push('one native-adapter morph in a 1000-field modal prepares the local root and settles cached pending factories without replaying ready siblings')
    await page.evaluate(async () => {
        const modal = document.createElement('section'); modal.id = 'parallel-morph'; modal.className = 'fi-modal fi-modal-open'; document.body.append(modal)
        const a = makeField(modal), b = makeField(modal), runtime = fixture.runtime({ document, window }); window.FffAssetInjector = runtime; runtime.boot()
        await runtime.prepareModal({ detail: { id: modal.id } }); await frames()
        a.field._fffFactoryReady = false; b.field._fffFactoryReady = false
        a.field.removeAttribute('data-fff-alpine-ready'); b.field.removeAttribute('data-fff-alpine-ready')
        runtime.applyPendingState(a.field); runtime.applyPendingState(b.field)
        const first = runtime.handleMorphUpdated({ el: a.field }), second = runtime.handleMorphUpdated({ el: b.field })
        await frames()
        a.field._fffFactoryReady = true; a.field.setAttribute('data-fff-alpine-ready', ''); a.field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true }))
        await frames()
        if (!runtime.hasPendingState(modal)) throw Error('One morph revealed another pending modal factory')
        b.field._fffFactoryReady = true; b.field.setAttribute('data-fff-alpine-ready', ''); b.field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true }))
        await Promise.all([first, second]); await frames()
        if (runtime.hasPendingState(modal) || runtime.getDiagnostics().records.length) throw Error('Shared modal barrier did not settle cleanly')
        runtime.dispose(); modal.remove(); delete window.FffAssetInjector
    })
    proofs.push('concurrent local modal morphs share readiness settlement; one completed factory cannot reveal its pending sibling')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: await browser.version(), minifiedSourceBuild: true, measurements, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve))
}
