import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'
import { readFileSync } from 'node:fs'

// Real DOM, MutationObserver and installed Alpine; no host routes or tests/** edits.
const root = path.resolve(import.meta.dirname, '../..')
const output = await build({ stdin: { resolveDir: root, contents: `
import { Alpine } from './vendor/livewire/livewire/dist/livewire.esm.js'
import { createConsumerGraph } from './resources/js/runtime/assets/consumer-graph.js'
import { createDomTransactions } from './resources/js/runtime/lifecycle/dom-transactions.js'
import { waitForAssetActivation } from './resources/js/runtime/lifecycle/asset-activation.js'
import { createFlexFieldAssetInjector } from './resources/js/runtime/asset-runtime.js'
import { observeDomAssets } from './resources/js/runtime/lifecycle/dom-assets-observer.js'
import { createResourceScope } from './resources/js/runtime/lifecycle/resource-scope.js'
import { createLivewireAdapter } from './resources/js/runtime/filament/livewire-adapter.js'
import { withFactoryReadiness } from './resources/js/runtime/alpine/factory-readiness.js'
import { isAssetElementActive } from './resources/js/runtime/lifecycle/asset-activation.js'
window.fixture = { Alpine, graph: createConsumerGraph, transactions: createDomTransactions, activate: waitForAssetActivation, active: isAssetElementActive, runtime: createFlexFieldAssetInjector, observe: observeDomAssets, scope: createResourceScope, adapter: createLivewireAdapter, factory: withFactoryReadiness }
window.frames = async () => { await new Promise(requestAnimationFrame); await new Promise(requestAnimationFrame) }
window.marker = (parent, styles = []) => {
 const el = document.createElement('span'); el.hidden = true; el.dataset.fffAssetBatch = ''; el.dataset.fffAssetConsumer = 'probe'; el.dataset.fffAssetConsumerId = 'same-ssr-key'; el.dataset.fffStylesheets = JSON.stringify(styles); el.dataset.fffChunks = '[]'; parent.append(el); return el
}
` }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const requests = []
const dist = path.join(root, 'resources/dist/components')
const manifest = JSON.parse(readFileSync(path.join(dist, 'alpine-manifest.json')))
const productionFiles = new Set([...manifest.__entries__.map(name => name + '.js'), ...manifest.__shared_chunks__])
const server = createServer((request, response) => {
    requests.push(request.url)
    const pathname = new URL(request.url, 'http://localhost').pathname
    if (pathname.startsWith('/production/') && productionFiles.has(pathname.slice('/production/'.length))) {
        response.writeHead(200, { 'Content-Type': 'text/javascript', 'Cache-Control': 'no-store' })
        response.end(readFileSync(path.join(dist, pathname.slice('/production/'.length)))); return
    }
    response.writeHead(200, { 'Content-Type': request.url === '/probe.js' ? 'text/javascript' : request.url === '/shared' ? 'text/css' : 'text/html', 'Cache-Control': 'no-store' })
    response.end(request.url === '/probe.js' ? output.outputFiles[0].contents : request.url === '/shared' ? '.locality-probe { color: rgb(0, 128, 0) }' : '<!doctype html><body><script type="module" src="/probe.js"></script>')
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = [], proofs = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.fixture)
    await page.evaluate(() => {
        const owner = document.createElement('section'); document.body.append(owner)
        const layout = document.createElement('div'); owner.append(layout)
        const el = marker(layout)
        for (const [attribute, value] of [['hidden', ''], ['aria-hidden', 'true'], ['data-fff-asset-active', 'false'],
            ['class', 'hidden'], ['class', 'invisible'], ['style', 'display: none !important'], ['style', 'visibility: hidden']]) {
            owner.setAttribute(attribute, value)
            if (fixture.active(el)) throw Error(`Hidden owner activated: ${attribute}=${value}`)
            owner.removeAttribute(attribute)
            if (!fixture.active(el)) throw Error(`Restored owner stayed inactive: ${attribute}`)
        }
        owner.setAttribute('style', 'display: grid; visibility: visible')
        if (!fixture.active(el)) throw Error('Visible styled owner was suspended')
        owner.removeAttribute('style')
        for (const [inactive, active] of [['fi-sc-tabs-tab', 'fi-sc-tabs-tab fi-active'],
            ['fi-sc-wizard-step', 'fi-sc-wizard-step fi-active'], ['fff-segment-tabs__panel', 'fff-segment-tabs__panel is-active']]) {
            owner.className = inactive
            if (fixture.active(el)) throw Error('Bare layout bypassed inactive parent: ' + inactive)
            owner.className = active
            if (!fixture.active(el)) throw Error('Active parent was suspended: ' + active)
        }
        owner.removeAttribute('class')
        const details = document.createElement('details'); owner.append(details); details.append(layout)
        if (fixture.active(el)) throw Error('Attribute-free DETAILS activated while closed')
        details.open = true
        if (!fixture.active(el)) throw Error('Open DETAILS stayed inactive')
        details.open = false
        if (fixture.active(el)) throw Error('Reclosed DETAILS retained an active value')
        const summary = document.createElement('summary'); details.append(summary); summary.append(layout)
        if (!fixture.active(el)) throw Error('Closed DETAILS suspended its summary control')
        owner.append(layout); details.remove()
        const section = document.createElement('section'); section.className = 'fi-section-content-ctn'
        owner.append(section); section.append(layout); owner.className = 'fi-collapsed'
        if (fixture.active(el)) throw Error('Activation selector skipped collapsed section content')
        owner.className = 'application-layout'; owner.id = 'unrelated-layout'
        if (!fixture.active(el)) throw Error('Unrelated application layout suspended its owner')
        document.body.hidden = true
        if (!fixture.active(el)) throw Error('Activation selector crossed its body boundary')
        document.body.hidden = false
        document.documentElement.hidden = true
        if (!fixture.active(el)) throw Error('Activation selector jumped past body to HTML')
        document.documentElement.hidden = false
        owner.remove()
    })
    proofs.push('native activation re-reads hidden/ARIA/explicit state, both hidden classes and inline CSS including !important; restoring each declaration activates without stale visibility caches; bare layout ancestors preserve inactive tabs/wizards/segments and closed DETAILS/summary semantics')
    const measurements = await page.evaluate(() => {
        const measurements = []
        for (const count of [100, 1000, 5000]) {
            const host = document.createElement('section'); document.body.append(host)
            let unrelated = 0, acquires = 0, releases = 0
            const graph = fixture.graph({ onAcquire: () => acquires++, onRelease: () => releases++ })
            const markers = Array.from({ length: count }, () => marker(host, ['/shared']))
            graph.resyncFromDom(host)
            for (const el of markers.slice(1)) Object.defineProperty(el, 'isConnected', { get() { unrelated++; return true }, configurable: true })
            const start = performance.now()
            for (let i = 0; i < 100; i++) graph.resyncFromDom(markers[0])
            const elapsedMs = performance.now() - start
            if (unrelated || acquires !== count || releases || graph.getRefCount('/shared') !== count) throw Error('Unrelated consumers inspected or ownership changed')
            measurements.push({ consumers: count, localResyncs: 100, unrelatedConnectionReads: unrelated, elapsedMs })
            graph.reset(); host.remove()
        }
        return measurements
    })
    proofs.push('real DOM at 100/1000/5000 consumers: 100 local reconciliations read zero unrelated connections and preserve shared references')
    await page.evaluate(async () => {
        const frames = new Map(); let sequence = 0
        const frameWindow = { requestAnimationFrame: callback => { const id = ++sequence; frames.set(id, callback); return id }, cancelAnimationFrame: id => frames.delete(id) }
        window.calls = []; window.life = fixture.scope(); window.graph = fixture.graph()
        const transactions = fixture.transactions({ window: frameWindow, prepare: async root => { calls.push(['prepare', root]); graph.resyncFromDom(root); return true }, ownership: root => { calls.push(['ownership', root]); graph.resyncFromDom(root) } })
        fixture.observe({ document, window, lifetime: life, transactions, graph, ensureAssets() { throw Error('Independent observer preparation') }, reportError: error => { throw error } })
        const adapter = fixture.adapter({ document, window, lifetime: life, transactions, crg: graph, assets: { resolvePendingTarget: el => el, ensureAssets() { throw Error('Independent morph preparation') } }, pending: { getPendingTargets: () => [] }, surfaces: { resolveAssetOwnerKey: () => 'page' }, injectorHooks: { shouldSkipBackgroundPreload: () => true } })
        const host = document.createElement('section'); document.body.append(host)
        const batch = marker(host, ['/shared']); window.host = host; window.batch = batch
        const morph = adapter.handleMorphUpdated({ el: host })
        await Promise.resolve(); await Promise.resolve() // Deliver native MutationObserver before this controlled frame.
        if (transactions.snapshot().roots !== 1) throw Error('Dirty roots duplicated')
        const pending = [...frames.values()]; frames.clear(); await Promise.all(pending.map(callback => callback())); await morph
        if (calls.length !== 1 || calls[0][0] !== 'prepare' || calls[0][1] !== host) throw Error('Observer and morph did not share preparation')
        life.dispose(); transactions.dispose(); graph.reset(); host.remove()
    })
    proofs.push('actual MutationObserver and Livewire adapter notifications prepare one merged dirty root, without separate ownership replay')
    await page.evaluate(async () => {
        window.checks = new Map(); window.controllers = []; window.waits = []; window.fields = []
        window.FffAssetInjector = { isElementActive: el => { checks.set(el, (checks.get(el) ?? 0) + 1); return !el.hidden && !el.parentElement.hidden } }
        const host = document.createElement('section'); document.body.append(host); window.waitHost = host
        for (let i = 0; i < 1000; i++) {
            const el = document.createElement('div'); el.hidden = true; host.append(el); fields.push(el)
            const controller = new AbortController(); controllers.push(controller); waits.push(fixture.activate(el, { signal: controller.signal }))
        }
        await Promise.resolve(); checks.clear(); fields[0].hidden = false; await waits[0]
        if (checks.size !== 1 || checks.get(fields[0]) !== 1) throw Error('Unrelated activation waiters checked')
        const wrapper = document.createElement('section'); wrapper.hidden = true; host.append(wrapper); wrapper.append(fields[1]); fields[1].hidden = false
        await Promise.resolve(); checks.clear(); wrapper.hidden = false; await waits[1]
        if (checks.size !== 1 || checks.get(fields[1]) !== 1) throw Error('Moved waiter missed or unrelated waiter checked')
        for (const controller of controllers) controller.abort()
        if ((await Promise.all(waits)).filter(Boolean).length !== 2) throw Error('Cancelled waiters activated')
        host.remove(); delete window.FffAssetInjector
    })
    proofs.push('1000 dormant real-DOM waiters: one changed owner checks once; moved waiter follows its new parent; all remaining waits abort')
    await page.evaluate(async () => {
        const el = document.createElement('div'); el.hidden = true; document.body.append(el)
        const controller = new AbortController(); const pending = fixture.activate(el, { signal: controller.signal })
        const body = document.createElement('body'); body.append(el); document.documentElement.replaceChild(body, document.body)
        document.dispatchEvent(new Event('livewire:navigated')); el.hidden = false
        if (await pending !== true) throw Error('Activation observer did not reconnect to new body')
        const cancelled = document.createElement('div'); cancelled.hidden = true; body.append(cancelled)
        const abort = new AbortController(); const wait = fixture.activate(cancelled, { signal: abort.signal }); cancelled.remove()
        if (await wait !== false) throw Error('Detached waiter retained')
        controller.abort(); abort.abort(); body.replaceChildren()
    })
    proofs.push('native body replacement reconnects activation observation; genuine subtree removal completes a dormant wait without activation')
    await page.evaluate(async () => {
        window.hooks = new Map(); window.Livewire = { hook(name, callback) { hooks.set(name, callback); return () => hooks.delete(name) } }
        window.Alpine = fixture.Alpine; window.destroyed = 0; window.initialized = 0
        fixture.Alpine.data('movementProbe', fixture.factory(() => ({ init() { initialized++ }, destroy() { destroyed++ } })))
        window.runtime = fixture.runtime({ document, window }); window.FffAssetInjector = runtime
        const a = document.createElement('section'), b = document.createElement('section'); document.body.append(a, b)
        const el = document.createElement('div'); el.className = 'locality-probe'; el.setAttribute('x-data', 'movementProbe()'); a.append(el)
        window.liveMarker = marker(el, ['/shared']); window.liveElement = el; window.a = a; window.b = b
        fixture.Alpine.start(); runtime.boot(); await runtime.ensureAssets(a)
        window.owner = runtime.getConsumerGraph().resolveConsumerInstanceId(liveMarker)
        hooks.get('morph.removed')({ el }); b.append(el); hooks.get('morph.added')({ el }); await frames()
        await runtime.ensureAssets(b)
        if (runtime.getConsumerGraph().resolveConsumerInstanceId(liveMarker) !== owner || initialized !== 1 || destroyed !== 0 || !el._fffFactoryReady) throw Error('Connected move recreated owner/factory')
        if (getComputedStyle(el).color !== 'rgb(0, 128, 0)' || runtime.getConsumerGraph().getRefCount(location.origin + '/shared') !== 1) throw Error('Shared CSS/ref lost while moving')
        const inspection = runtime.explainOwner(liveElement)
        if (inspection.owners.length !== 1 || !inspection.owners[0].retained || !inspection.owners[0].resources[0].loaded
            || inspection.factories.length !== 1 || inspection.factories[0].state !== 'ready') throw Error('Full runtime omitted live instance explanation')
    })
    assert.equal(requests.filter(url => url === '/shared').length, 1)
    proofs.push('full runtime, installed Alpine and native observer: same-document move preserves one factory/init, readiness, owner ID and one actual HTTP stylesheet request')
    await page.evaluate(async () => {
        liveElement.remove(); hooks.get('morph.removed')({ el: liveElement }); await frames()
        if (destroyed !== 1 || runtime.getConsumerGraph().getRefCount(location.origin + '/shared') !== 0) throw Error('Detached factory or claim not released')
        if (runtime.getDiagnostics().records.length) throw Error('Runtime reported unexpected failure')
        runtime.dispose(); fixture.Alpine.stopObservingMutations(); a.remove(); b.remove()
        if (runtime.explainOwner(null).status !== 'disposed') throw Error('Retired runtime continued inspecting DOM')
        if (hooks.size) throw Error('Runtime hooks retained')
    })
    proofs.push('genuine detach destroys the factory once and releases its typed reference; final runtime disposal removes hooks without diagnostic errors')
    // Production-distribution profile, separately from source contract probes.
    const cdp = await page.context().newCDPSession(page), productionProfiles = []
    for (const rate of [1, 4]) {
        await cdp.send('Emulation.setCPUThrottlingRate', { rate })
        await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: rate === 4 ? 40 : 0,
            downloadThroughput: rate === 4 ? 2000000 : -1, uploadThroughput: rate === 4 ? 2000000 : -1 })
        await page.setViewportSize(rate === 1 ? { width: 1280, height: 900 } : { width: 390, height: 844 })
        await page.goto(`http://127.0.0.1:${server.address().port}/production-profile`)
        const profile = await page.evaluate(async rate => {
            const coldStart = performance.now()
            const { installCoreRuntime } = await import('/production/flex-core-runtime.js')
            const installStart = performance.now()
            const runtime = installCoreRuntime(), sdk = globalThis[Symbol.for('fff.sdk.v1')]
            const synchronousRuntimeInstallMs = performance.now() - installStart
            await runtime.ensureAssets(document)
            const coldBootstrapWallMs = performance.now() - coldStart
            const host = document.createElement('section'); document.body.append(host)
            const markers = Array.from({ length: 1000 }, () => marker(host))
            const graph = runtime.getConsumerGraph(); graph.resyncFromDom(host)
            await runtime.ensureAssets(host)
            await frames()
            const local = [], activation = [], batch = []
            const sorted = samples => { const copy = [...samples].sort((a, b) => a - b); return Object.fromEntries([['p50', .5], ['p95', .95], ['p99', .99]].map(([key, value]) => [key, copy[Math.ceil(copy.length * value) - 1]])) }
            for (let sample = 0; sample < 100; sample++) {
                let start = performance.now(); graph.resyncFromDom(markers[0]); local.push(performance.now() - start)
                start = performance.now(); await runtime.ensureAssets(markers[0]); activation.push(performance.now() - start)
                start = performance.now(); graph.resyncFromDom(host); batch.push(performance.now() - start)
                if (sample % 10 === 0) await new Promise(requestAnimationFrame)
            }
            graph.releaseSubtree(host); host.remove(); host.replaceChildren(); markers.length = 0
            const cycles = async count => {
                for (let cycle = 0; cycle < count; cycle++) {
                    const owner = document.createElement('section'); owner.dataset.fffAssetActive = 'false'; document.body.append(owner); marker(owner)
                    const handle = await sdk.createAssetSurface(owner); await handle.activate(); handle.close(); handle.dispose(); owner.remove()
                    if (cycle % 10 === 0) await new Promise(requestAnimationFrame)
                }
                await frames()
            }
            await cycles(50); window.productionCycles = cycles
            return { rate, owners: 1000, samples: 100, controlled_network_latency_ms: rate === 4 ? 40 : 0,
                cold_bootstrap_network_and_runtime_wall_ms: coldBootstrapWallMs,
                synchronous_runtime_install_ms: synchronousRuntimeInstallMs, batch_reconcile_1000_ms: sorted(batch),
                local_ms: sorted(local), warm_activation_ms: sorted(activation),
                longest_core_operation_ms: Math.max(...local, ...activation, ...batch), diagnostics: runtime.getDiagnostics().records.length }
        }, rate)
        assert.ok(profile.local_ms.p95 <= (rate === 1 ? 4 : 8), JSON.stringify(profile))
        assert.ok(profile.batch_reconcile_1000_ms.p95 <= (rate === 1 ? 4 : 8), JSON.stringify(profile))
        assert.ok(profile.warm_activation_ms.p95 <= 16.67, JSON.stringify(profile))
        assert.ok(profile.longest_core_operation_ms <= 50, JSON.stringify(profile))
        assert.equal(profile.diagnostics, 0)
        const heaps = []
        for (const count of [0, 1000, 1000]) {
            await page.evaluate(count => productionCycles(count), count)
            await cdp.send('HeapProfiler.collectGarbage')
            heaps.push({ ...(await cdp.send('Runtime.getHeapUsage')), ...(await cdp.send('Memory.getDOMCounters')) })
        }
        assert.equal(heaps[2].documents, heaps[1].documents)
        assert.equal(heaps[2].nodes, heaps[1].nodes)
        assert.equal(heaps[2].jsEventListeners, heaps[1].jsEventListeners)
        assert.ok(heaps[2].usedSize - heaps[1].usedSize <= 131072, JSON.stringify(heaps))
        productionProfiles.push({ ...profile, mount_close_dispose_cycles: 2000, heaps, physicalDevice: false })
    }
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 })
    proofs.push('actual production runtime: 1000 owners, desktop/mobile CPU×4 p50/p95/p99, warm readiness and 2000 public surface cycles per profile; post-GC DOM/listeners and heap plateau budgets')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: await browser.version(), minifiedSourceBuild: true, installedAlpine: true, measurements, productionProfiles, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
