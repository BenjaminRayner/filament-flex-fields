import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { createServer } from 'node:http'
import { execFileSync } from 'node:child_process'
import { launchCoreBrowser, browserEngine } from '../compatibility/browser-profile.mjs'
import { digest, sourceSnapshot } from './acceptance-contract.mjs'
import { safeReleaseFile, verifyReleaseSeal } from './release-metadata.mjs'
import { startupCases, startupFields, startupBudgets, startupResourcePolicy, assertStartupResourceBudget, summarizeStartup } from './startup-cost.mjs'
import { verifyNativeStartupCycles } from './native-startup-cycles.mjs'
import { verifyNativeSelectPortals } from './native-select-portals.mjs'

const root = path.resolve(import.meta.dirname, '../..')
let cpuRate = 1, networkProfile = 'loopback', headed = false
let repetitions = 5, verifyDynamic = false, cycles = 0, output = path.join(root, 'build/core-reports/startup-cost.json')
const seen = new Set()
for (const arg of process.argv.slice(2)) {
    const [key, ...rest] = arg.split('=')
    assert.ok(['--samples', '--output', '--verify-dynamic', '--cycles', '--cpu-rate', '--network-profile', '--headed'].includes(key) && !seen.has(key) && rest.join('=').length, 'Unknown or repeated startup argument.')
    seen.add(key)
    if (key === '--samples') { assert.match(rest.join('='), /^\d+$/); repetitions = Number(rest[0]) }
    else if (key === '--verify-dynamic') { assert.equal(rest.join('='), 'true'); verifyDynamic = true }
    else if (key === '--headed') { assert.equal(rest.join('='), 'true'); headed = true }
    else if (key === '--cpu-rate') { assert.match(rest.join('='), /^(1|4)$/); cpuRate = Number(rest[0]) }
    else if (key === '--network-profile') { assert.ok(['loopback', 'cdn-simulated'].includes(rest.join('='))); networkProfile = rest.join('=') }
    else if (key === '--cycles') { assert.match(rest.join('='), /^\d+$/); cycles = Number(rest[0]) }
    else output = path.resolve(root, rest.join('='))
}
assert.ok(Number.isInteger(repetitions) && repetitions >= 1 && repetitions <= 50, 'Use 1–50 samples per case and context profile.')
assert.ok(Number.isInteger(cycles) && cycles >= 0 && cycles <= 100 && (!cycles || verifyDynamic), 'Native cycles require --verify-dynamic=true; use 0–100 cycles.')
assert.ok(!fs.existsSync(output), 'Choose a new output path; previous startup evidence is never overwritten.')
assert.ok(browserEngine === 'chromium' || (cpuRate === 1 && networkProfile === 'loopback'), 'Throttled profiles require Chromium CDP.')
const sources = sourceSnapshot(root)
const seal = JSON.parse(safeReleaseFile(root, 'resources/dist/asset-versions.json'))
verifyReleaseSeal(root, seal)
const resourcePolicy = startupResourcePolicy(safeReleaseFile(root, 'resources/dist/components/alpine-manifest.json'), seal)
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'flex-startup-'))
fs.writeFileSync(path.join(directory, '.startup-fixture'), '')
const php = process.env.PHP_BINARY ?? 'php'
const render = name => JSON.parse(execFileSync(php, ['scripts/core/fixtures/startup-panel.php', name, directory], {
    cwd: root, encoding: 'utf8', timeout: 120000, maxBuffer: 8 * 1024 * 1024,
}).trim())
const samples = [], serverErrors = []
let browser, server, failure, failedSample, environment, rendering, dynamicMount, nativeLifecycle, nestedModal, returningFilamentTab, selectPortals
try {
    environment = render('publish')
    const pages = [...startupCases, ...(verifyDynamic ? ['dynamic', 'tabs', 'portal', 'portal-page'] : [])].map(render)
    const cookies = Object.fromEntries(pages.map(page => [page.case, page.cookies]))
    // Fixture session cookies stay ephemeral, never in the exported evidence.
    rendering = pages.map(({ cookies: ignored, ...page }) => page)
    // Use the installed Livewire distribution, never a replacement Alpine runtime.
    const livewire = path.join(root, 'vendor/livewire/livewire/dist/livewire.min.js')
    const html = Object.fromEntries([...startupCases, ...(verifyDynamic ? ['dynamic', 'tabs', 'portal', 'portal-page'] : [])].map(name => [name, fs.readFileSync(path.join(directory, name + '.html'), 'utf8')]))
    let nativeRequests = 0
    server = createServer((request, response) => {
        const url = new URL(request.url, 'http://localhost')
        if (verifyDynamic && request.method === 'POST' && /^\/livewire-[a-z0-9]+\/update$/.test(url.pathname)) {
            nativeRequests++
            let body = '', oversized = false
            request.on('data', bytes => { body += bytes.toString(); if (Buffer.byteLength(body) > 2 * 1024 * 1024) { oversized = true; request.destroy() } })
            request.on('end', () => {
                if (oversized) return
                try {
                    const raw = execFileSync(php, ['scripts/core/fixtures/startup-panel.php', 'dynamic', directory, 'request'], {
                        cwd: root, input: JSON.stringify({ uri: url.pathname, body, cookies: Object.fromEntries((request.headers.cookie ?? '').split(';')
                            .filter(value => value.includes('=')).map(value => { const index = value.indexOf('='); return [value.slice(0, index).trim(), decodeURIComponent(value.slice(index + 1))] })) }), encoding: 'utf8', timeout: 30000, maxBuffer: 8 * 1024 * 1024,
                    })
                    let result
                    try { result = JSON.parse(raw) } catch { throw Error('Invalid fixture response: ' + raw.slice(0, 4000)) }
                    const origin = `http://127.0.0.1:${server.address().port}`
                    response.writeHead(result.status, { 'Content-Type': 'application/json' })
                    response.end(result.body.replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin))
                } catch (error) { serverErrors.push(error.message); response.writeHead(500); response.end('{}') }
            })
            return
        }
        const mode = url.searchParams.get('case')
        if (url.pathname === '/cost' && Object.hasOwn(html, mode)) {
            const origin = `http://127.0.0.1:${server.address().port}`
            response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store', 'Set-Cookie': cookies[mode] })
            response.end(html[mode].replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin)); return
        }
        if (url.pathname === '/favicon.ico') { response.writeHead(204); response.end(); return }
        const base = fs.realpathSync(path.join(directory, 'public'))
        const requested = path.resolve(base, '.' + decodeURIComponent(url.pathname))
        let file = null
        if (/^\/livewire-[a-z0-9]+\/livewire\.min\.js$/.test(url.pathname)) file = livewire
        else if (requested.startsWith(base + path.sep) && fs.existsSync(requested)) {
            const resolved = fs.realpathSync(requested)
            if (resolved.startsWith(base + path.sep) && fs.statSync(resolved).isFile()) file = resolved
        }
        if (!file) {
            serverErrors.push('Missing fixture asset: ' + url.pathname)
            response.writeHead(404); response.end(); return
        }
        const type = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' }[path.extname(file)] ?? 'application/octet-stream'
        response.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'public, max-age=3600' }); response.end(fs.readFileSync(file))
    })
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    const origin = `http://127.0.0.1:${server.address().port}`
    browser = await launchCoreBrowser({ headless: !headed })
    environment = { ...environment, browser: await browser.version(), engine: browserEngine, node: process.version, platform: process.platform, architecture: process.arch, viewport: { width: 1440, height: 1000 } }
    const visit = async (context, name, mode, sample, collect) => {
        const page = await context.newPage(), errors = []
        const profiler = browserEngine === 'chromium' ? await context.newCDPSession(page) : null
        let metricStart = null
        const parseTrace = { events: 0, durationMs: 0, compileEvents: 0, compileDurationMs: 0 }
        let traceActive = false
        const stopTrace = async () => {
            if (!traceActive) return
            const completed = new Promise(resolve => profiler.once('Tracing.tracingComplete', resolve))
            await profiler.send('Tracing.end'); await completed; traceActive = false
        }
        if (profiler) {
            await profiler.send('Performance.enable')
            profiler.on('Tracing.dataCollected', ({ value }) => {
                for (const event of value) {
                    if (event.ph !== 'X' || !Number.isFinite(event.dur) || event.dur < 0 || !String(event.cat).includes('v8')) continue
                    if (/parse/i.test(event.name)) { parseTrace.events++; parseTrace.durationMs += event.dur / 1000 }
                    else if (/compile/i.test(event.name)) { parseTrace.compileEvents++; parseTrace.compileDurationMs += event.dur / 1000 }
                }
            })
            await profiler.send('Tracing.start', { categories: 'v8,disabled-by-default-v8.compile,devtools.timeline', transferMode: 'ReportEvents' })
            traceActive = true
            await profiler.send('Emulation.setCPUThrottlingRate', { rate: cpuRate })
            if (networkProfile === 'cdn-simulated') await profiler.send('Network.emulateNetworkConditions', { offline: false, latency: 80, downloadThroughput: 20 * 1024 * 1024 / 8, uploadThroughput: 5 * 1024 * 1024 / 8 })
            metricStart = Object.fromEntries((await profiler.send('Performance.getMetrics')).metrics.map(metric => [metric.name, metric.value]))
        }
        page.setDefaultTimeout(20000)
        page.on('pageerror', error => errors.push(error.message))
        page.on('requestfailed', request => errors.push(request.url() + ': ' + request.failure()?.errorText))
        page.on('response', response => { if (response.status() >= 400) errors.push(response.status() + ': ' + response.url()) })
        await page.addInitScript(count => {
            window.__startupBoundaryReadyAt = null
            window.__startupFactoryInit = []
            document.addEventListener('alpine:init', () => {
                const original = window.Alpine.data
                window.Alpine.data = function (name, factory) {
                    return original.call(this, name, function (...args) {
                        const data = factory.apply(this, args)
                        if (!data || typeof data.init !== 'function' || typeof data.showStatus !== 'function' || !('runtimeDiagnostics' in data)) return data
                        const init = data.init
                        data.init = function (...values) {
                            const start = performance.now()
                            const record = () => window.__startupFactoryInit.push(performance.now() - start)
                            try {
                                const result = init.apply(this, values)
                                if (result && typeof result.then === 'function') return Promise.resolve(result).finally(record)
                                record(); return result
                            } catch (error) { record(); throw error }
                        }
                        return data
                    })
                }
            }, { once: true })
            const checkReadiness = () => {
                if (document.readyState === 'loading') return
                const boundaries = [...document.querySelectorAll('[data-fff-asset-consumer]')]
                if (boundaries.length === count && boundaries.every(node => node.hasAttribute('data-fff-assets-ready'))) {
                    window.__startupBoundaryReadyAt = performance.now(); observer.disconnect()
                }
            }
            const observer = new MutationObserver(checkReadiness)
            observer.observe(document, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-fff-assets-ready'] })
            document.addEventListener('DOMContentLoaded', checkReadiness, { once: true })
            window.__startupLongTasks = []
            if (PerformanceObserver.supportedEntryTypes.includes('longtask')) new PerformanceObserver(list => {
                for (const task of list.getEntries()) window.__startupLongTasks.push({ start: task.startTime, duration: task.duration })
            }).observe({ type: 'longtask', buffered: true })
        }, startupFields[name])
        try {
            const response = await page.goto(origin + '/cost?case=' + name, { waitUntil: 'domcontentloaded' })
            assert.equal(response.status(), 200)
            await page.waitForFunction(count => {
                const boundaries = [...document.querySelectorAll('[data-fff-asset-consumer]')]
                return boundaries.length === count && boundaries.every(node => node.hasAttribute('data-fff-assets-ready')) && window.__startupBoundaryReadyAt !== null
            }, startupFields[name])
            // Consumer asset readiness and asynchronous Alpine factory completion
            // are distinct observations; await both instead of sampling one frame too early.
            await page.waitForFunction(count => {
                const inputs = [...document.querySelectorAll('[data-fff-text-input]')]
                return inputs.length === count && inputs.every(input => input._fffFactoryReady === true)
                    && window.__startupFactoryInit.length === count
            }, startupFields[name])
            // Use actual readiness and font/paint completion. A generic network-idle
            // heuristic can stall on cached/preloaded Firefox requests after every owner is ready.
            await page.evaluate(async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(resolve)) })
            assert.deepEqual(errors, [])
            const result = await page.evaluate(() => ({
                boundaries: document.querySelectorAll('[data-fff-asset-consumer]').length,
                ready: document.querySelectorAll('[data-fff-asset-consumer][data-fff-assets-ready]').length,
                boundaryReadyMs: window.__startupBoundaryReadyAt, observationCompletedMs: performance.now(), domContentLoadedMs: performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd,
                resources: performance.getEntriesByType('resource').map(resource => ({ path: new URL(resource.name).pathname,
                    hasQuery: new URL(resource.name).search.length > 0, hasFragment: new URL(resource.name).hash.length > 0,
                    local: new URL(resource.name).origin === location.origin,
                    flex: new URL(resource.name).pathname.startsWith('/filament-flex-fields-assets/'),
                    initiator: resource.initiatorType, decoded: resource.decodedBodySize, encoded: resource.encodedBodySize, transfer: resource.transferSize })),
                longTasks: window.__startupLongTasks,
                factoryInitMs: window.__startupFactoryInit,
            }))
            assert.ok(result.resources.every(resource => resource.local), 'Unexpected external asset.')
            if (name !== 'native') {
                result.resourceBudget = assertStartupResourceBudget(result.resources, startupFields[name] > 0, resourcePolicy)
                if (name === 'enabled-empty') assert.equal(await page.evaluate(() => !!window.FffAssetInjector), false, 'Empty panel eagerly installed the full runtime.')
            }
            if (profiler) {
                await stopTrace()
                result.parseTrace = { ...parseTrace, observed: parseTrace.events > 0,
                    scope: 'Summed V8 parse/compile trace-event durations for the renderer, including native framework scripts. Nested or background events may overlap; not additive wall-clock time or FLEX-only CPU.' }
                const metrics = Object.fromEntries((await profiler.send('Performance.getMetrics')).metrics.map(metric => [metric.name, metric.value]))
                result.renderer = {
                    scriptMs: Math.max(0, metrics.ScriptDuration - metricStart.ScriptDuration) * 1000,
                    taskMs: Math.max(0, metrics.TaskDuration - metricStart.TaskDuration) * 1000,
                    layoutMs: Math.max(0, metrics.LayoutDuration - metricStart.LayoutDuration) * 1000,
                    styleMs: Math.max(0, metrics.RecalcStyleDuration - metricStart.RecalcStyleDuration) * 1000,
                    heapUsedBytes: metrics.JSHeapUsedSize, heapTotalBytes: metrics.JSHeapTotalSize,
                    domNodes: metrics.Nodes, eventListeners: metrics.JSEventListeners,
                    scope: 'CDP renderer counters at settled startup; script duration includes evaluation and execution, not a standalone parse-time measurement. Heap is not a retained-leak measurement.',
                }
            }
            if (collect && name === 'hundred' && sample === 0) {
                const before = await page.evaluate(() => window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().sort())
                const other = await context.newPage()
                await other.goto('about:blank'); await other.bringToFront()
                const hiddenState = await page.evaluate(() => document.visibilityState)
                if (headed) assert.equal(hiddenState, 'hidden', 'The headed tab did not enter the background.')
                await page.bringToFront(); await other.close()
                const after = await page.evaluate(() => ({ visibility: document.visibilityState, owners: window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().sort(),
                    ready: document.querySelectorAll('[data-fff-asset-consumer][data-fff-assets-ready]').length }))
                assert.deepEqual(after.owners, before, 'Returning to a tab replaced its active owners.')
                assert.equal(after.ready, startupFields[name])
                result.returningTab = { hiddenState, ...after, scope: 'Actual second browser page and bringToFront; visibility state is reported rather than assumed. Owner identity and all 100 ready consumers must survive.' }
            }
            assert.equal(result.factoryInitMs.length, startupFields[name], 'Factory init instrumentation missed a real input.')
            if (collect) samples.push({ case: name, mode, sample, ...result, errors })
        } catch (error) { failedSample = { case: name, mode, sample, errors }; throw error }
        finally { await stopTrace(); await page.close() }
    }
    const contextOptions = { viewport: environment.viewport, serviceWorkers: 'block' }
    const primed = new Map()
    try {
        for (const name of startupCases) {
            const context = await browser.newContext(contextOptions); primed.set(name, context)
            await visit(context, name, 'primed-context', -1, false)
        }
        for (let sample = 0; sample < repetitions; sample++) {
            // Rotate the order to avoid always measuring the same case first.
            const order = [...startupCases.slice(sample % startupCases.length), ...startupCases.slice(0, sample % startupCases.length)]
            for (const name of order) {
                const context = await browser.newContext(contextOptions)
                try { await visit(context, name, 'fresh-context', sample, true) } finally { await context.close() }
                await visit(primed.get(name), name, 'primed-context', sample, true)
            }
            console.log(`Startup sample ${sample + 1}/${repetitions}: five cases, two context profiles passed`)
        }
    } finally { for (const context of primed.values()) await context.close() }
    if (verifyDynamic) {
        const context = await browser.newContext(contextOptions), page = await context.newPage(), errors = []
        let releaseRuntime, runtimeRequested = false, browserNativeRequests = 0
        const held = new Promise(resolve => { releaseRuntime = resolve })
        page.on('pageerror', error => errors.push(error.message))
        page.on('requestfailed', request => errors.push(request.url() + ': ' + request.failure()?.errorText))
        page.on('response', response => { if (response.status() >= 400) errors.push(response.status() + ': ' + response.url()) })
        page.on('request', request => { if (request.method() === 'POST' && /\/livewire-[a-z0-9]+\/update$/.test(new URL(request.url()).pathname)) browserNativeRequests++ })
        await page.route('**/components/flex-core-runtime.js', async route => { runtimeRequested = true; await held; await route.continue() })
        try {
            await page.goto(origin + '/cost?case=dynamic', { waitUntil: 'networkidle' })
            assert.equal(runtimeRequested, false, 'Empty panel eagerly imported runtime.')
            assert.equal(await page.locator('[data-fff-asset-consumer]').count(), 0)
            const demanded = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/components/flex-core-runtime.js'))
            await page.getByRole('button', { name: 'Open first FLEX field', exact: true }).click()
            await demanded
            // Controlled observation window, not a production readiness delay.
            await page.waitForTimeout(75)
            assert.equal(browserNativeRequests, 0, 'Browser sent the native request while runtime remained held.')
            assert.equal(nativeRequests, 0, 'Native request escaped before startup.')
            releaseRuntime()
            const input = page.getByRole('textbox', { name: /^Message/ })
            await input.waitFor({ state: 'visible', timeout: 20000 })
            await page.waitForFunction(() => {
                const root = document.querySelector('.fi-modal.fi-modal-open')
                return root && !root.hasAttribute('aria-busy') && [...root.querySelectorAll('[x-fff-load]')]
                    .every(node => node._fffFactoryReady === true)
            }, null, { timeout: 20000 })
            await input.fill('First dynamic owner is ready')
            assert.equal(await input.inputValue(), 'First dynamic owner is ready')
            assert.equal(nativeRequests, 1)
            assert.equal(browserNativeRequests, 1)
            assert.deepEqual(errors, [])
            dynamicMount = { passed: true, nativeRequests, browserNativeRequests, errors, proof: 'An initially empty native panel defers the original Action while runtime import is held; after release its real Livewire request renders the first FLEX field in a native modal and awaits factory readiness before interaction.' }
            await page.getByRole('button', { name: 'Open child FLEX field', exact: true }).click()
            const child = page.getByRole('textbox', { name: /^Child message/ })
            await child.waitFor({ state: 'visible' })
            await page.waitForFunction(() => [...document.querySelectorAll('[data-fff-text-input]')].every(node => node._fffFactoryReady === true))
            await child.fill('Nested owner is ready')
            const nestedOwners = await page.evaluate(() => window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().length)
            assert.equal(nestedOwners, 2, 'Nested modal did not preserve its suspended parent owner.')
            await page.getByRole('button', { name: 'Cancel', exact: true }).last().click()
            await input.waitFor({ state: 'visible' })
            assert.equal(await input.inputValue(), 'First dynamic owner is ready')
            await page.waitForFunction(() => window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().length === 1)
            nestedModal = { passed: true, childAndParentOwners: nestedOwners, remainingParentOwners: 1, parentValuePreserved: true }
            if (cycles) nativeLifecycle = await verifyNativeStartupCycles(page, cycles)
            selectPortals = await verifyNativeSelectPortals(page, origin)
            assert.deepEqual(errors, [])
        } catch (error) {
            dynamicMount = { passed: false, nativeRequests, errors,
                snapshot: await page.evaluate(() => ({ text: document.body.innerText.slice(-2500),
                    consumers: [...document.querySelectorAll('[data-fff-asset-consumer]')].map(el => el.outerHTML),
                    runtime: !!window.FffAssetInjector, modals: [...document.querySelectorAll('.fi-modal')].map(el => ({ classes: el.className, busy: el.getAttribute('aria-busy') })),
                    lifecycle: window.__nativeCycleProof ? { mounted: window.__nativeCycleProof.mounted, retired: window.__nativeCycleProof.retired,
                        navigations: window.__nativeCycleProof.navigations, timers: window.__nativeCycleProof.timers.size } : null,
                })).catch(() => null) }
            throw error
        } finally { releaseRuntime(); await context.close() }
    }
    if (verifyDynamic) {
        const context = await browser.newContext(contextOptions), page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        try {
            await page.goto(origin + '/cost?case=tabs', { waitUntil: 'domcontentloaded' })
            await page.getByRole('tab', { name: 'Basics', exact: true }).waitFor({ state: 'visible' })
            assert.equal(await page.evaluate(() => !!document.querySelector('[data-fff-text-input]')?._fffFactoryReady), false, 'Inactive native tab initialized its field.')
            await page.getByRole('tab', { name: 'FLEX details', exact: true }).click()
            const input = page.getByRole('textbox', { name: 'Tab message', exact: true })
            await input.waitFor({ state: 'visible' })
            await page.waitForFunction(() => document.querySelector('[data-fff-text-input]')?._fffFactoryReady === true)
            await input.fill('Preserved when returning')
            const scriptsBefore = await page.evaluate(() => performance.getEntriesByType('resource').filter(r => new URL(r.name).pathname.endsWith('/flex-text-input.js')).length)
            await page.getByRole('tab', { name: 'Basics', exact: true }).click()
            await input.waitFor({ state: 'hidden' })
            await page.waitForFunction(() => window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().length === 0)
            await page.getByRole('tab', { name: 'FLEX details', exact: true }).click()
            await input.waitFor({ state: 'visible' })
            await page.waitForFunction(() => document.querySelector('[data-fff-asset-consumer][data-fff-assets-ready]'))
            assert.equal(await input.inputValue(), 'Preserved when returning')
            const scriptsAfter = await page.evaluate(() => performance.getEntriesByType('resource').filter(r => new URL(r.name).pathname.endsWith('/flex-text-input.js')).length)
            assert.equal(scriptsAfter, scriptsBefore, 'Returning to a native tab reloaded its evaluated module.')
            assert.deepEqual(errors, [])
            returningFilamentTab = { passed: true, initialFactoryDeferred: true, inactiveOwners: 0, valuePreserved: true, moduleResourceEntriesBefore: scriptsBefore, moduleResourceEntriesAfter: scriptsAfter }
        } finally { await context.close() }
    }
    assert.deepEqual(serverErrors, [])
    assert.deepEqual(sourceSnapshot(root), sources, 'Source changed during startup measurement.')
} catch (error) { failure = error }
finally {
    await browser?.close()
    if (server?.listening) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)) }
    const report = { format: 1, measuredAt: new Date().toISOString(), passed: !failure,
        commit: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim(),
        sourceSha256: digest(JSON.stringify(sources)), release: seal.release, environment, rendering,
        profile: 'Native Filament panel HTML rendered once per case in separate Testbench boots; matching native and FLEX assets published into an isolated directory. Browser navigations use those real SSR documents on loopback, with no recording overrides or replacement controls.',
        limitations: ['Browser startup profile, not production PHP TTFB, interaction latency, retained-heap leak certification or FPS.',
            'Local uncompressed HTTP with optional explicit CDN transport simulation, max-age=3600; primed contexts may reuse cache. Decoded bytes are payload entries, not network transfer.',
            'boundaryReadyMs observes ready consumer markers after DOMContentLoaded; it does not prove all optional interactions or later Livewire updates. A shared observer adds measurement overhead.',
            'Shared development machine; explicit CPU throttling is a relative browser setting, not a hardware certificate. No cold OS/disk or production CDN claim.',
            'Fixture-only wrappers measure real FlexTextInput init promises; instrumentation and V8 tracing add overhead. Parse/compile event durations may overlap.',
            'PHP boot/render metrics are single observations; browser distributions are repeated.'],
        cpuRate, networkProfile, headed, networkEmulation: networkProfile === 'cdn-simulated' ? { latencyMs: 80, downloadMbps: 20, uploadMbps: 5, actualCdn: false } : null,
        repetitions, samples, dynamicMount, nestedModal, returningFilamentTab, nativeLifecycle, selectPortals, serverErrors, ...(failure ? { error: failure.stack, failedSample } : { summary: summarizeStartup(samples, repetitions) }) }
    fs.mkdirSync(path.dirname(output), { recursive: true }); fs.writeFileSync(output, JSON.stringify(report, null, 2) + '\n')
    fs.rmSync(directory, { recursive: true, force: true })
    if (!failure) console.log(JSON.stringify({ passed: true, sourceSha256: report.sourceSha256, release: seal.release,
        summary: report.summary, dynamicMount: report.dynamicMount, nativeLifecycle: report.nativeLifecycle }))
    console.log('Startup evidence: ' + output)
}
if (failure) throw failure
