import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'
import { createFlexSdkPlugin } from '../assets/flex-sdk-plugin.mjs'

const root = path.resolve(import.meta.dirname, '../..'), dist = path.join(root, 'resources/dist/components')
const manifest = JSON.parse(fs.readFileSync(path.join(dist, 'alpine-manifest.json'), 'utf8'))
const allowed = new Set([...manifest.__entries__.map(name => `${name}.js`), ...manifest.__shared_chunks__])
const author = await build({ stdin: { contents: `import {createResourceScope, createLifecycleEvents} from '@bjanczak/flex-fields/sdk/v1';
export const effects = options => createResourceScope(options); export const events = options => createLifecycleEvents(options);`, resolveDir: root },
    absWorkingDir: root, bundle: true, format: 'esm', minify: true, write: false, metafile: true, plugins: [createFlexSdkPlugin()] })
assert.deepEqual(Object.keys(author.metafile.inputs).sort(), ['<stdin>', 'fff-runtime-sdk:v1'])
const errors = [], requests = [], proofs = []
let cssStartedResolve, finishCss, cssRequests = 0
const cssStarted = new Promise(resolve => { cssStartedResolve = resolve })
const server = createServer((request, response) => {
    if (request.url === '/') { response.writeHead(200, { 'Content-Type': 'text/html' }); return response.end('<!doctype html><html><head><meta charset="utf-8"></head><body><div id="target"></div></body></html>') }
    if (request.url === '/author.js') { response.writeHead(200, { 'Content-Type': 'text/javascript' }); return response.end(author.outputFiles[0].contents) }
    if (request.url === '/favicon.ico') { response.writeHead(204); return response.end() }
    if (request.url === '/held.css') {
        cssRequests++; cssStartedResolve()
        finishCss = () => { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.fixture { color: inherit }') }
        return
    }
    const name = request.url.slice(1)
    if (allowed.has(name)) {
        requests.push(name); response.writeHead(200, { 'Content-Type': 'text/javascript' })
        return response.end(fs.readFileSync(path.join(dist, name)))
    }
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage()
    // The control channel must not queue behind the very CSS response it releases.
    await page.exposeFunction('waitForProbeStylesheet', () => cssStarted)
    await page.exposeFunction('releaseProbeStylesheet', () => finishCss())
    page.on('pageerror', error => errors.push(error.message))
    const result = await page.goto(`http://127.0.0.1:${server.address().port}/`)
    assert.equal(result.status(), 200)
    const value = await page.evaluate(async () => {
        const assert = (ok, label) => { if (!ok) throw Error(label) }
        const wait = ms => new Promise(resolve => setTimeout(resolve, ms))
        const unhandled = []; window.addEventListener('unhandledrejection', event => unhandled.push(String(event.reason)))
        const sdk = await import('/flex-runtime-sdk.js'), author = await import('/author.js')
        assert(typeof sdk.createLifecycleEvents === 'function', 'Published SDK lacks events')
        let rejected = false
        try { author.effects() } catch (error) { rejected = /not installed/.test(error.message) }
        assert(rejected, 'Author facade used an absent runtime')
        const runtime = await import('/flex-core-runtime.js'), injector = runtime.installCoreRuntime()
        const installed = globalThis[Symbol.for('fff.sdk.v1')]
        assert(installed.createLifecycleEvents === sdk.createLifecycleEvents && installed.createResourceScope === sdk.createResourceScope, 'SDK/runtime implementations differ')
        const proofs = ['native published SDK and runtime share event/effect implementations; actual author facade embeds no private sources and fails without installation']
        const events = { 'example.photo:changed': { kind: 'notification', validate: detail => typeof detail.id === 'string' },
            'example.photo:before-save': { kind: 'guard', validate: detail => typeof detail.id === 'string' } }
        const parent = new AbortController(), child = new AbortController(), received = [], reports = []
        const bus = author.events({ events, signal: parent.signal, onError: report => { reports.push(report); return Promise.reject(Error('reporter')) } })
        bus.subscribe('example.photo:changed', () => { throw Error('private value') })
        bus.subscribe('example.photo:changed', () => Promise.reject(Error('private rejected value')))
        const listener = detail => received.push(detail.id)
        bus.subscribe('example.photo:changed', listener, { signal: child.signal })
        const remove = bus.subscribe('example.photo:changed', listener)
        assert(bus.notify('example.photo:changed', { id: 'one' }) && received.length === 2, 'Independent callbacks lost')
        child.abort(); remove(); bus.notify('example.photo:changed', { id: 'two' })
        assert(received.length === 2 && bus.snapshot().groups === 2, 'Individual cancellation leaked')
        const asyncGuard = bus.subscribe('example.photo:before-save', () => Promise.reject(Error('async guard')))
        assert(!bus.allow('example.photo:before-save', { id: 'one' }), 'Async guard granted save')
        asyncGuard()
        bus.subscribe('example.photo:before-save', () => { parent.abort(); return true })
        assert(!bus.allow('example.photo:before-save', { id: 'disposed' }), 'Disposed guard granted save')
        assert(bus.snapshot().groups === 0 && bus.snapshot().ownedResources === 0, 'Parent cancellation leaked callbacks')
        await wait(20)
        assert(!JSON.stringify(reports).includes('private'), 'Telemetry contains field data')
        proofs.push('native signal interests, thrown/rejected handlers, reporting failures, async guards and parent close isolate remaining owners with zero private telemetry')

        const abort = new AbortController(), scope = author.effects({ signal: abort.signal }), target = document.querySelector('#target')
        let calls = 0
        scope.listen(target, 'click', () => calls++)
        target.click(); assert(calls === 1, 'Native listener missing')
        const base = scope.snapshot().resources
        await new Promise(resolve => scope.frame(() => resolve()))
        assert(scope.snapshot().resources === base, 'Completed frame retained bookkeeping')
        let lateFrame = 0, lateTimer = 0, observed = 0, workerReady = 0, workerLate = 0
        scope.frame(() => lateFrame++)
        scope.timeout(() => lateTimer++, 20)
        const observer = new MutationObserver(() => observed++)
        observer.observe(target, { attributes: true }); scope.trackObserver(observer)
        const source = scope.objectUrl(new Blob(['postMessage("ready"); setTimeout(()=>postMessage("late"),150)'], { type: 'text/javascript' }))
        const worker = new Worker(source.url); scope.trackWorker(worker)
        await new Promise(resolve => scope.listen(worker, 'message', event => {
            if (event.data === 'ready') { workerReady++; resolve() } else workerLate++
        }))
        // Schedule a fresh frame immediately before abort; the earlier frame may
        // have legitimately completed while the worker started.
        let cancelledFrame = 0; scope.frame(() => cancelledFrame++)
        abort.abort(); scope.dispose()
        const before = calls; target.click(); target.setAttribute('data-proof', 'closed')
        assert(scope.signal.aborted && scope.snapshot().resources === 0, 'Native cleanup left resources')
        await wait(180)
        assert(calls === before && cancelledFrame === 0 && observed === 0 && workerReady === 1 && workerLate === 0, 'Native effects published after cancellation')
        proofs.push('actual requestAnimationFrame, DOM listeners, MutationObserver, Worker and object URL are owned; abort cancels pending frame/worker and releases all bookkeeping')
        const fresh = author.events({ events }); let reopened = 0
        fresh.subscribe('example.photo:changed', () => reopened++)
        fresh.notify('example.photo:changed', { id: 'fresh' }); fresh.dispose()
        assert(reopened === 1, 'Closed owner prevented fresh owner')

        const hookOwner = new AbortController(), decisions = []
        const removeOld = injector.registerInjectorHooks({ shouldSkipBackgroundPreload: () => { decisions.push('old'); return false } })
        injector.registerInjectorHooks({ shouldSkipBackgroundPreload: () => { decisions.push('new'); return true } }, { signal: hookOwner.signal })
        removeOld(); hookOwner.abort()
        const cancelled = new AbortController()
        injector.registerInjectorHooks({ markPendingStarted: () => { cancelled.abort(); decisions.push('cancelled') } }, { signal: cancelled.signal })
        injector.applyPendingState(target); await injector.releasePendingState(target)
        injector.applyPendingState(target); await injector.releasePendingState(target)
        assert(decisions.filter(value => value === 'cancelled').length === 1, 'Actual runtime hook interest survived abort')
        proofs.push('published runtime accepts independent hook signals and idempotent release; aborting inside its native pending hook prevents later callback reuse')

        const modal = document.createElement('div'); modal.id = 'owned-attempt'; modal.className = 'fi-modal fi-modal-open'
        const field = document.createElement('div'); field.setAttribute('x-fff-load', ''); field._fffFactoryReady = false
        const batch = document.createElement('span'); batch.hidden = true; batch.dataset.fffAssetBatch = ''
        batch.dataset.fffAssetConsumer = 'example'; batch.dataset.fffAssetConsumerId = 'example.modal'
        batch.dataset.fffComponent = 'example'; batch.dataset.fffStylesheets = JSON.stringify(['/held.css']); batch.dataset.fffChunks = '[]'
        const pageBatch = batch.cloneNode(); pageBatch.dataset.fffAssetConsumerId = 'example.page'
        document.body.append(pageBatch); const pagePreparation = injector.ensureAssets(pageBatch)
        field.append(batch); modal.append(field); document.body.append(modal)
        const event = { detail: { id: modal.id } }, oldPreparation = injector.prepareModal(event)
        await window.waitForProbeStylesheet()
        modal.classList.remove('fi-modal-open'); await injector.cleanupClosedModalPendingState(event)
        modal.classList.add('fi-modal-open'); const freshPreparation = injector.prepareModal(event)
        await window.releaseProbeStylesheet(); await pagePreparation; await oldPreparation
        assert(injector.hasPendingState(modal), 'Old completion revealed fresh factory wait')
        field._fffFactoryReady = true; field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true }))
        await freshPreparation
        assert(!injector.hasPendingState(modal) && !modal.querySelector('.fff-flex-fields-assets-skeleton'), 'Fresh modal did not settle')

        // Close a separate attempt after assets are already ready, while it is
        // observing factory init; the close signal must settle that wait now.
        field._fffFactoryReady = false; field.removeAttribute('data-fff-alpine-ready')
        const waitingPreparation = injector.prepareModal(event)
        await new Promise(requestAnimationFrame)
        modal.classList.remove('fi-modal-open'); await injector.cleanupClosedModalPendingState(event); await waitingPreparation
        assert(!injector.hasPendingState(modal), 'Closed factory wait retained pending surface')
        modal.remove(); pageBatch.remove()
        proofs.push('published native modal runtime shares held HTTP CSS across close/reopen; obsolete success cannot reveal fresh init, and close aborts an actual factory observer/deadline')
        const revisionModal = document.createElement('div'); revisionModal.id = 'revision-modal'; revisionModal.className = 'fi-modal fi-modal-open'
        const revisionWindow = document.createElement('div'); revisionWindow.className = 'fi-modal-window'; revisionModal.append(revisionWindow)
        const revisionField = document.createElement('div'); revisionField.setAttribute('x-fff-load', ''); revisionField._fffFactoryReady = false
        revisionWindow.append(revisionField); document.body.append(revisionModal)
        const revisionEvent = { detail: { id: revisionModal.id } }
        const frames = async () => { for (let i = 0; i < 3; i++) await new Promise(requestAnimationFrame) }
        const revisionOpening = injector.prepareModal(revisionEvent); await frames()
        await injector.cleanupClosedModalPendingState(revisionEvent); await revisionOpening
        // Opening needs no IO, but still waits for this instance's init.
        revisionField._fffFactoryReady = true
        revisionField.setAttribute('data-fff-alpine-ready', ''); await injector.prepareModal(revisionEvent)
        let delay = 30000
        const releaseDelay = injector.registerInjectorHooks({ getPendingReleaseDelayMs: () => delay })
        const timers = new Set(), nativeTimeout = window.setTimeout, nativeClear = window.clearTimeout
        window.setTimeout = function(callback, milliseconds, ...args) {
            const handle = nativeTimeout.call(this, callback, milliseconds, ...args)
            if (milliseconds === 30000) timers.add(handle)
            return handle
        }
        window.clearTimeout = function(handle) { timers.delete(handle); return nativeClear.call(this, handle) }
        try {
            revisionField._fffFactoryReady = false; revisionField.removeAttribute('data-fff-alpine-ready')
            injector.applyPendingState(revisionField)
            const oldMorph = injector.handleMorphUpdated({ el: revisionField }); await frames()
            revisionField._fffFactoryReady = true; revisionField.setAttribute('data-fff-alpine-ready', '')
            revisionField.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true })); await frames()
            assert(timers.size === 1 && injector.hasPendingState(revisionModal), 'Actual delayed release did not wait')
            const replacement = revisionField.cloneNode(); replacement._fffFactoryReady = false; replacement.removeAttribute('data-fff-alpine-ready')
            revisionField.replaceWith(replacement); injector.applyPendingState(replacement); delay = 0
            const freshMorph = injector.handleMorphUpdated({ el: replacement }); await oldMorph; await frames()
            assert(timers.size === 0 && injector.hasPendingState(revisionModal), 'Obsolete release timer survived or revealed replacement')
            revisionField._x_async = 'failed'; revisionField.dispatchEvent(new CustomEvent('fff-load-error', { bubbles: true }))
            assert(!revisionModal.classList.contains('fff-flex-fields-assets-failed'), 'Detached old factory resurrected retry UI')
            replacement._fffFactoryReady = true; replacement.setAttribute('data-fff-alpine-ready', '')
            replacement.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true })); await freshMorph
            assert(!injector.hasPendingState(revisionModal), 'Current morph did not reveal after its init')
            replacement._fffFactoryReady = false; replacement.removeAttribute('data-fff-alpine-ready'); injector.applyPendingState(replacement)
            const navigationMorph = injector.handleMorphUpdated({ el: replacement }); await frames()
            document.dispatchEvent(new Event('livewire:navigating')); await navigationMorph
            assert(injector.hasPendingState(revisionModal) && !revisionModal.classList.contains('fff-flex-fields-assets-failed'), 'Navigation revealed obsolete morph or rendered retry')
            await injector.cleanupClosedModalPendingState(revisionEvent)
            assert(timers.size === 0, 'Closed morph retained delayed release')
            proofs.push('actual published morph revision aborts a native30-second release timer immediately; obsolete detached factory errors cannot reveal/fail its replacement; fresh init reveals once and navigation aborts pending factory settlement')
        } finally {
            releaseDelay(); window.setTimeout = nativeTimeout; window.clearTimeout = nativeClear; revisionModal.remove()
        }
        injector.dispose()
        await wait(20); assert(unhandled.length === 0, 'Unhandled async rejection')
        proofs.push('new owner remains independent after close; runtime disposal and async completion leave no unhandled rejection')
        const observe = window.MutationObserver, activeObservers = new Set(), attached = new Map()
        class TrackedObserver extends observe {
            observe(...args) { activeObservers.add(this); return super.observe(...args) }
            disconnect() { activeObservers.delete(this); return super.disconnect() }
        }
        const originals = [document, window].map(target => ({ target, add: target.addEventListener, remove: target.removeEventListener }))
        for (const { target, add, remove } of originals) {
            const entries = new Map(); attached.set(target, entries)
            target.addEventListener = function(name, callback, options) { entries.set(callback, name); return add.call(this, name, callback, options) }
            target.removeEventListener = function(name, callback, options) { entries.delete(callback); return remove.call(this, name, callback, options) }
        }
        window.MutationObserver = TrackedObserver
        const baseline = () => {
            assert(activeObservers.size === 0, 'Failed startup retained native observer')
            assert([...attached.values()].every(entries => entries.size === 0), 'Failed startup retained native listener')
            assert(!window.FffAssetInjector && !window.__fffFlexFieldAssetInjector, 'Failed startup retained runtime globals')
        }
        try {
            const callbacks = [], releases = []
            window.Livewire = {
                interceptMessage(callback) { callbacks.push(callback); return () => { releases.push('intercept'); throw Error('cleanup') } },
                hook(name, callback) { callbacks.push(callback); if (name === 'morph.adding') throw Error('startup'); return () => releases.push(name) },
            }
            let failure
            try { runtime.installCoreRuntime() } catch (error) { failure = error }
            assert(failure?.message === 'startup', 'Original startup error was replaced')
            baseline(); assert(releases.join(',') === 'morph.updating,intercept', 'Native rollback order is incorrect')
            window.Livewire = null
            const retry = runtime.installCoreRuntime()
            for (const callback of callbacks) callback({ el: target, toEl: target, message: {}, onSuccess() { throw Error('obsolete') } })
            retry.dispose(); baseline()
            proofs.push('actual built runtime rolls back native listeners and MutationObserver after partial framework failure despite a throwing disposer; fresh installation succeeds and obsolete native callbacks stay inert')

            const waiting = runtime.installCoreRuntime()
            window.Livewire = { hook() { throw Error('late') } }
            document.dispatchEvent(new Event('livewire:init'))
            assert(waiting.isDisposed(), 'Late framework failure retained live runtime'); baseline()
            window.Livewire = null
            const readyState = Object.getOwnPropertyDescriptor(document, 'readyState')
            Object.defineProperty(document, 'readyState', { configurable: true, value: 'loading' })
            try {
                for (let i = 0; i < 100; i++) {
                    const waiting = runtime.installCoreRuntime()
                    assert([...attached.get(document).values()].filter(name => name === 'DOMContentLoaded').length === 1, 'DOM readiness wait duplicated')
                    waiting.dispose(); document.dispatchEvent(new Event('DOMContentLoaded')); baseline()
                }
            } finally {
                if (readyState) Object.defineProperty(document, 'readyState', readyState)
                else delete document.readyState
            }
            proofs.push('actual native document listeners under controlled loading state clean up over100 cancelled ready waits; late livewire:init failure retires the complete started runtime without page errors')
        } finally {
            window.FffAssetInjector?.dispose()
            window.MutationObserver = observe; delete window.Livewire
            for (const { target, add, remove } of originals) { target.addEventListener = add; target.removeEventListener = remove }
        }
        await wait(20); assert(unhandled.length === 0, 'Unhandled startup rejection')
        return { proofs, unhandled }
    })
    proofs.push(...value.proofs)
    assert.deepEqual(value.unhandled, []); assert.deepEqual(errors, [])
    assert.equal(cssRequests, 1, 'Close/reopen duplicated shared CSS transport')
    assert.equal(requests.length, new Set(requests).size, 'Shared implementation fetched twice')
    console.log(JSON.stringify({ browser: await browser.version(), actualBuiltEntries: true, authorPrivateInputs: 0, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
