import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-author-browser-'))
const installedIndex = process.argv.indexOf('--installed-app')
const installedApplication = installedIndex < 0 ? null : process.argv[installedIndex + 1]
if (installedIndex >= 0) assert.ok(installedApplication && path.isAbsolute(installedApplication), 'Installed application needs an absolute owned fixture path')
const packageRoot = installedApplication ? path.join(installedApplication, 'vendor/acceptance/summary') : path.join(temporary, 'package')
const coreRoot = installedApplication ? path.join(installedApplication, 'vendor/janczakb/filament-flex-fields') : root
const prefix = installedApplication ? 'acceptance-summary' : 'browser-summary'
let browser, server, cdnServer
const errors = [], requests = [], proofs = []
let activeAuthorRequests = 0, maximumActiveAuthorRequests = 0
try {
    if (!installedApplication) {
        const generated = spawnSync(process.env.PHP_BINARY || 'php', ['-r', `
require 'vendor/autoload.php';
(new Bjanczak\\FilamentFlexFields\\Support\\Components\\Authoring\\ComponentPackageGenerator)->generate(
new Bjanczak\\FilamentFlexFields\\Support\\Components\\Authoring\\ComponentPackageBlueprint('browser/summary', 'browser-summary', 'Browser\\\\Summary', 'Summary'), $argv[1]);
`, packageRoot], { cwd: root, encoding: 'utf8' })
        assert.equal(generated.status, 0, generated.stderr)
        fs.symlinkSync(path.join(root, 'node_modules'), path.join(packageRoot, 'node_modules'), 'dir')
        const compiled = spawnSync(process.execPath, ['build.mjs'], { cwd: packageRoot, encoding: 'utf8', env: { ...process.env, FLEX_FIELDS_ROOT: root } })
        assert.equal(compiled.status, 0, compiled.stderr)
    } else {
        assert.equal(fs.lstatSync(packageRoot).isSymbolicLink(), false)
        assert.equal(fs.lstatSync(coreRoot).isSymbolicLink(), false)
    }
    const manifest = JSON.parse(fs.readFileSync(path.join(packageRoot, 'resources/asset-manifest.json')))
    const seal = JSON.parse(fs.readFileSync(path.join(coreRoot, 'resources/dist/asset-versions.json')))
    const corePrefix = installedApplication ? `/filament-flex-fields-assets/core/${seal.release}/components/` : '/core/'
    const authorPrefix = installedApplication ? `/filament-flex-fields-assets/packages/${manifest.package}/${manifest.release}/` : '/author/'
    const dist = installedApplication ? path.join(installedApplication, 'custom-public', corePrefix) : path.join(root, 'resources/dist/components')
    const authorDist = installedApplication ? path.join(installedApplication, 'custom-public', authorPrefix) : path.join(packageRoot, 'resources/dist', manifest.release)
    const secondManifest = installedApplication ? JSON.parse(fs.readFileSync(path.join(installedApplication, 'vendor/acceptance/status/resources/asset-manifest.json'))) : null
    const secondPrefix = secondManifest ? `/filament-flex-fields-assets/packages/${secondManifest.package}/${secondManifest.release}/` : null
    const coreManifest = JSON.parse(fs.readFileSync(path.join(coreRoot, 'resources/dist/components/alpine-manifest.json')))
    const allowed = new Set([...coreManifest.__entries__.map(name => `${name}.js`), ...coreManifest.__shared_chunks__])
    const alpinePath = (installedApplication ? [path.join(installedApplication, 'vendor/livewire/livewire/dist/livewire.esm.js')] : [path.join(root, 'vendor/livewire/livewire/dist/livewire.esm.js'), path.resolve(root, '../../vendor/livewire/livewire/dist/livewire.esm.js')]).find(file => fs.existsSync(file))
    assert.ok(alpinePath, 'Installed Livewire ESM is unavailable')
    const alpine = await build({ stdin: { resolveDir: root, contents: `import { Alpine, Livewire } from ${JSON.stringify(alpinePath)}; window.Alpine = Alpine; export { Alpine, Livewire };` },
        bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
    const schemaPath = path.join(installedApplication ?? path.resolve(root, '../..'), 'vendor/filament/schemas/resources/js/index.js')
    const schemas = installedApplication ? await build({ entryPoints: [schemaPath],
        bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' }) : null
    const serve = (request, response) => {
        requests.push(request.url)
        const send = (type, body, status = 200) => {
            const immutable = request.url.startsWith('/filament-flex-fields-assets/') || request.url.startsWith('/author/') || request.url.startsWith('/core/')
            response.writeHead(status, { 'Content-Type': type, 'Cache-Control': immutable ? 'public, max-age=31536000, immutable' : 'no-store', 'Access-Control-Allow-Origin': '*' }); response.end(body)
        }
        const pathname = new URL(request.url, 'http://localhost').pathname
        if (installedApplication && ['/composition', '/composition-morph', '/composition-cdn'].includes(pathname)) {
            const panelTwo = new URL(request.url, 'http://localhost').searchParams.get('panel') === 'second'
            const name = pathname === '/composition-morph' ? 'composition-morph.html' : panelTwo ? 'composition-panel.html' : 'composition.html'
            const origin = `http://127.0.0.1:${server.address().port}`
            let html = fs.readFileSync(path.join(installedApplication, name), 'utf8')
                .replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin.replaceAll('/', '\\/'))
            if (pathname === '/composition-cdn') {
                const cdn = `http://127.0.0.1:${cdnServer.address().port}`
                html = html.replaceAll(`${origin}/filament-flex-fields-assets/`, `${cdn}/filament-flex-fields-assets/`)
                    .replaceAll(`${origin.replaceAll('/', '\\/')}\\/filament-flex-fields-assets\\/`, `${cdn.replaceAll('/', '\\/')}\\/filament-flex-fields-assets\\/`)
            }
            return send('text/html', html)
        }
        if (request.url === '/') return send('text/html', '<!doctype html><body><section id="owners"><div id="first" x-data="summaryFieldComponent()"><input></div><div id="second" x-data="summaryFieldComponent()"><input></div></section>')
        if (request.url === '/alpine.js') return send('text/javascript', alpine.outputFiles[0].contents)
        if (request.url === '/schemas.js' && schemas) return send('text/javascript', schemas.outputFiles[0].contents)
        if (request.url.startsWith(corePrefix) && allowed.has(request.url.slice(corePrefix.length))) return send('text/javascript', fs.readFileSync(path.join(dist, request.url.slice(corePrefix.length))))
        const publicCore = `/filament-flex-fields-assets/core/${seal.release}/`
        if (installedApplication && pathname.startsWith(publicCore) && Object.hasOwn(seal.assets, pathname.slice(publicCore.length))) {
            const name = pathname.slice(publicCore.length)
            return send(name.endsWith('.css') ? 'text/css' : 'text/javascript', fs.readFileSync(path.join(installedApplication, 'custom-public', publicCore, name)))
        }
        const artifact = Object.values(manifest.artifacts).find(value => `${authorPrefix}${value.path}` === pathname)
        if (artifact) {
            if (artifact === manifest.artifacts[manifest.entries[`${prefix}-field`]]) {
                maximumActiveAuthorRequests = Math.max(maximumActiveAuthorRequests, ++activeAuthorRequests)
                response.once('close', () => { activeAuthorRequests-- })
            }
            return send(artifact.type === 'stylesheet' ? 'text/css' : artifact.type === 'wasm' ? 'application/wasm' : 'text/javascript', fs.readFileSync(path.join(authorDist, artifact.path)))
        }
        const secondArtifact = secondManifest && Object.values(secondManifest.artifacts).find(value => `${secondPrefix}${value.path}` === pathname)
        if (secondArtifact) return send(secondArtifact.type === 'stylesheet' ? 'text/css' : 'text/javascript', fs.readFileSync(path.join(installedApplication, 'custom-public', secondPrefix, secondArtifact.path)))
        return send('text/plain', 'not found', 404)
    }
    server = createServer(serve)
    cdnServer = createServer(serve)
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    await new Promise(resolve => cdnServer.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage()
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`)
    const entry = manifest.artifacts[manifest.entries[`${prefix}-field`]].path
    const entryUrl = `${authorPrefix}${entry}`
    const result = await page.evaluate(async ({ entryUrl, corePrefix, prefix }) => {
        const { Alpine } = await import('/alpine.js')
        const core = await import(`${corePrefix}flex-core-runtime.js`)
        core.installCoreRuntime()
        const module = await import(entryUrl)
        const repeated = await import(entryUrl)
        window.authorModule = module
        const sdk = globalThis[Symbol.for('fff.sdk.v1')]
        const originalScope = sdk.createResourceScope
        window.events = []
        document.addEventListener(`fff-${prefix}-changed`, event => window.events.push(event.target.id))
        Alpine.data('summaryFieldComponent', module.default)
        Alpine.start()
        window.detachedInput = document.querySelector('#first input')
        window.detachedInput.dispatchEvent(new Event('input', { bubbles: true }))
        document.querySelector('#second input').dispatchEvent(new Event('input', { bubbles: true }))
        return { same: module === repeated, events: window.events, abi: sdk.sdkABI, sameScope: sdk.createResourceScope === originalScope }
    }, { entryUrl, corePrefix, prefix })
    assert.deepEqual(result, { same: true, events: ['first', 'second'], abi: 1, sameScope: true })
    assert.equal(requests.filter(url => url === entryUrl).length, 1)
    proofs.push('actual generated ESM uses installed compiled SDK; native ESM fetched once, two real Alpine factories mount and dispatch independently')
    await page.evaluate(() => document.querySelector('#first').remove())
    await page.evaluate(() => {})
    const afterRemove = await page.evaluate(() => {
        window.detachedInput.dispatchEvent(new Event('input', { bubbles: true }))
        document.querySelector('#second input').dispatchEvent(new Event('input', { bubbles: true }))
        return window.events
    })
    assert.deepEqual(afterRemove, ['first', 'second', 'second'])
    // Detached bubbling cannot reach document: also spy on the detached owner itself.
    const cleanup = await page.evaluate(prefix => {
        let dispatched = 0
        window.detachedInput.parentElement.addEventListener(`fff-${prefix}-changed`, () => dispatched++)
        window.detachedInput.dispatchEvent(new Event('input', { bubbles: true }))
        return dispatched
    }, prefix)
    assert.equal(cleanup, 0)
    proofs.push('Alpine removal disposes generated SDK listener; detached input emits nothing while remaining owner stays operational')
    await page.evaluate(() => { document.querySelector('#owners').insertAdjacentHTML('beforeend', '<div id="third" x-data="summaryFieldComponent()"><input></div>') })
    await page.waitForFunction(() => !!document.querySelector('#third')._x_dataStack)
    const remounted = await page.evaluate(() => {
        document.querySelector('#third input').dispatchEvent(new Event('input', { bubbles: true }))
        return window.events
    })
    assert.deepEqual(remounted, ['first', 'second', 'second', 'third'])
    assert.equal(requests.filter(url => url === entryUrl).length, 1)
    if (installedApplication) {
        const optional = Object.values(manifest.artifacts).filter(artifact => artifact.type === 'module' && artifact.path !== entry)
        const hardware = Object.values(manifest.artifacts).filter(artifact => ['worker', 'wasm'].includes(artifact.type))
        assert.equal(hardware.length, 2)
        assert.equal(requests.filter(url => hardware.some(artifact => url === `${authorPrefix}${artifact.path}`)).length, 0)
        assert.equal(requests.filter(url => optional.some(artifact => url === `${authorPrefix}${artifact.path}`)).length, 0)
        assert.equal(await page.evaluate(async () => (await window.authorModule.optional()).optional), true)
        assert.equal(requests.filter(url => optional.some(artifact => url === `${authorPrefix}${artifact.path}`)).length, 1)
        proofs.push('independently Composer-installed lazy module stays dormant until invoked and resolves from the published immutable package URL')
        assert.equal(requests.filter(url => hardware.some(artifact => url === `${authorPrefix}${artifact.path}`)).length, 0)
        const captured = await page.evaluate(async () => (await window.authorModule.optional()).capture())
        assert.deepEqual(captured, { wasm: true, bytes: 8 })
        for (const artifact of hardware) assert.equal(requests.filter(url => url === `${authorPrefix}${artifact.path}`).length, 1)
        proofs.push('independently installed lazy worker and native WASM execute only on demand, each published dependency fetched exactly once; worker and timeout disposed')

        const composition = await browser.newPage(), start = requests.length
        composition.on('pageerror', error => errors.push(error.message))
        await composition.goto(`http://127.0.0.1:${server.address().port}/composition`)
        await composition.evaluate(async corePrefix => {
            window.__fffAuthorFixture = { inits: {}, successes: {}, disposals: {}, fail: new Set(), pending: new Map() }
            const { Livewire } = await import('/alpine.js')
            await import('/schemas.js')
            window.Livewire = Livewire
            window.livewireScriptConfig = { uri: '/livewire/update', csrf: 'fixture-token' }
            const core = await import(`${corePrefix}flex-core-runtime.js`)
            core.installCoreRuntime(); Livewire.start()
        }, corePrefix)
        await composition.waitForFunction(() => [...document.querySelectorAll('[data-fff-text-input]')]
            .filter(node => !node.closest('[hidden]')).every(node => node._fffFactoryReady === true))
        const memorySession = browserEngine === 'chromium' ? await composition.context().newCDPSession(composition) : null
        const memorySnapshot = async () => {
            if (!memorySession) return null
            await memorySession.send('HeapProfiler.collectGarbage')
            const dom = await memorySession.send('Memory.getDOMCounters')
            const heap = await memorySession.send('Runtime.getHeapUsage')
            return { ...dom, usedBytes: heap.usedSize }
        }
        const memoryBefore = await memorySnapshot()
        assert.equal(requests.slice(start).filter(url => new URL(url, 'http://localhost').pathname === entryUrl).length, 0)
        const before = await composition.evaluate(() => {
            const one = document.querySelector('#composition-one input'), two = document.querySelector('#composition-two input')
            one.value = 'first'; one.dispatchEvent(new Event('input', { bubbles: true }))
            return { one: one.value, two: two.value, maximums: [one.maxLength, two.maxLength], ids: [one.id, two.id],
                labels: [one, two].map(input => [...input.labels].map(label => label.textContent.trim())),
                required: [one.required, two.required],
                sizes: [one.closest('.fff-flex-text-input'), two.closest('.fff-flex-text-input')]
                    .map(root => ({ small: root.classList.contains('fff-flex-text-input--sm'), large: root.classList.contains('fff-flex-text-input--lg'), height: root.getBoundingClientRect().height })),
                readonly: [...document.querySelectorAll('#composition-one input, #composition-two input')].filter(input => input.readOnly).length }
        })
        assert.deepEqual(before.maximums, [6, 12]); assert.equal(before.two, 'two'); assert.notEqual(...before.ids)
        assert(before.labels[0].some(label => label.includes('en')) && before.labels[1].some(label => label.includes('pl')))
        assert.deepEqual(before.required, [true, true]); assert.equal(before.readonly, 2)
        assert.equal(before.sizes[0].small, true); assert.equal(before.sizes[1].large, true)
        assert(before.sizes[0].height < before.sizes[1].height, 'Mapped small/large child sizing did not reach the real shared renderer CSS')
        proofs.push('Composer-installed A uses real typed B/C/D renderers with different child limits/locale labels, stable IDs and independent values; closed D makes zero author-module requests')
        await composition.evaluate(async () => {
            const sdk = globalThis[Symbol.for('fff.sdk.v1')]
            window.compositionOwners = {}
            for (const name of ['one', 'two']) {
                const parent = await sdk.createAssetSurface(document.getElementById(`composition-${name}`))
                await parent.activate()
                const dialogRoot = document.getElementById(`dialog-${name}`)
                const child = await sdk.createAssetSurface(dialogRoot, { parent })
                document.body.appendChild(dialogRoot)
                window.compositionOwners[name] = { parent, child, root: dialogRoot }
            }
        })
        let releaseImport, importStarted
        const importing = new Promise(resolve => { importStarted = resolve })
        await composition.route(`**${entryUrl}`, async route => {
            await new Promise(resolve => { releaseImport = resolve; importStarted() })
            await route.continue()
        })
        await composition.evaluate(() => {
            window.oldActivation = window.compositionOwners.one.child.activate()
        })
        await importing
        assert.equal(await composition.evaluate(async () => {
            window.compositionOwners.one.child.close()
            return await window.oldActivation
        }), false)
        assert.equal(await composition.evaluate(() => window.__fffAuthorFixture.inits['dialog-one'] ?? 0), 0, 'Cancelled import initialized a factory before its replacement activation')
        await composition.evaluate(() => {
            const fixture = window.__fffAuthorFixture
            fixture.fail.add('dialog-one')
            window.failedActivation = window.compositionOwners.one.child.activate().catch(() => false)
        })
        releaseImport()
        assert.equal(await composition.evaluate(() => window.failedActivation), false)
        assert.equal(await composition.evaluate(() => window.compositionOwners.one.child.ready), false)
        assert.equal(await composition.locator('input[data-browser-independent]').inputValue(), 'outside')
        const failedAttempts = await composition.evaluate(() => ({ attempts: window.__fffAuthorFixture.inits['dialog-one'], successes: window.__fffAuthorFixture.successes['dialog-one'] ?? 0 }))
        assert(failedAttempts.attempts > 0)
        assert.equal(failedAttempts.successes, 0)
        await composition.unroute(`**${entryUrl}`)
        await composition.evaluate(async () => {
            window.__fffAuthorFixture.fail.delete('dialog-one')
            const current = window.compositionOwners.one
            if (await current.child.activate()) current.root.hidden = false
        })
        await composition.waitForFunction(() => window.compositionOwners.one.child.ready)
        const lifecycle = await composition.evaluate(() => window.__fffAuthorFixture.inits['dialog-one'])
        assert.equal(lifecycle, failedAttempts.attempts + 1, 'Explicit retry initialized more than one replacement')
        assert.equal(await composition.evaluate(() => window.__fffAuthorFixture.successes['dialog-one']), 1)
        const controlledFaults = errors.filter(error => error.includes('Controlled author D initialization failure'))
        assert.equal(controlledFaults.length, failedAttempts.attempts, 'Every failed factory attempt must report exactly once')
        for (const fault of controlledFaults) errors.splice(errors.indexOf(fault), 1)
        proofs.push('actual Composer-installed D import cancels without reveal; factory initialization failure stays local, B remains usable and one explicit retry mounts the replacement exactly once')
        // WebKit refetches after the routed/cancelled preload; this is sequential
        // retry, not a second active owner flight or another module evaluation.
        const authorRequestsAfterRetry = requests.slice(start).filter(url => new URL(url, 'http://localhost').pathname === entryUrl).length
        assert.equal(authorRequestsAfterRetry, browserEngine === 'webkit' ? 2 : 1)
        assert.equal(await composition.evaluate(() => window.__fffAuthorFixture.moduleEvaluations), 1)
        assert.equal(maximumActiveAuthorRequests, 1)
        proofs.push(`installed cancelled-import retry uses ${authorRequestsAfterRetry} sequential HTTP responses, one active author flight and one module evaluation; further owners reuse this module`)
        await composition.evaluate(() => {
            let resolve
            const promise = new Promise(finish => { resolve = finish })
            window.__fffAuthorFixture.pending.set('dialog-two', { promise, resolve })
            window.pendingOldInit = window.compositionOwners.two.child.activate()
        })
        await composition.waitForFunction(() => window.__fffAuthorFixture.successes['dialog-two'] === 1)
        assert.equal(await composition.evaluate(() => window.compositionOwners.two.child.ready), false)
        await composition.evaluate(async () => {
            const current = window.compositionOwners.two
            window.oldInitGate = window.__fffAuthorFixture.pending.get('dialog-two')
            current.child.dispose()
            const replacement = current.root.cloneNode(true)
            replacement.dataset.authorConfiguration = 'replacement'
            current.root.replaceWith(replacement)
            current.root = replacement
            let resolve
            const promise = new Promise(finish => { resolve = finish })
            window.__fffAuthorFixture.pending.set('dialog-two', { promise, resolve })
            current.child = await globalThis[Symbol.for('fff.sdk.v1')].createAssetSurface(replacement, { parent: current.parent })
            window.pendingNewInit = current.child.activate()
        })
        await composition.waitForFunction(() => window.__fffAuthorFixture.successes['dialog-two'] === 2)
        assert.equal(await composition.evaluate(() => window.pendingOldInit), false)
        await composition.evaluate(() => window.oldInitGate.resolve())
        assert.equal(await composition.evaluate(() => window.compositionOwners.two.child.ready), false, 'Completion of the removed D init revealed its pending replacement')
        assert.equal(await composition.locator('#dialog-two').evaluate(root => root.hidden), true)
        await composition.evaluate(() => window.__fffAuthorFixture.pending.get('dialog-two').resolve())
        assert.equal(await composition.evaluate(() => window.pendingNewInit), true)
        assert.equal(await composition.evaluate(() => window.__fffAuthorFixture.disposals['dialog-two']), 1)
        proofs.push('replacing installed D during async author init disposes its old listener once; late old completion cannot reveal the pending new generation; only the new init acknowledgement activates it without another module fetch')
        await composition.evaluate(async () => {
            const current = window.compositionOwners.two
            if (await current.child.activate()) current.root.hidden = false
            window.compositionOwners.one.parent.dispose()
            document.getElementById('composition-one').remove()
            window.compositionOwners.one.root.remove()
        })
        assert.equal(await composition.evaluate(() => window.compositionOwners.two.child.ready), true)
        assert.equal(requests.slice(start).filter(url => new URL(url, 'http://localhost').pathname === entryUrl).length, authorRequestsAfterRetry)
        assert.equal(await composition.locator(`link[href*="${authorPrefix}"][href$="/display.css"]`).count(), 1)
        assert.equal(await composition.locator(`link[href*="${secondPrefix}"][href$="/display.css"]`).count(), 1)
        const visual = await composition.evaluate(() => {
            const status = document.querySelector('.fff-acceptance-status-value')
            const summary = document.querySelector('.fff-acceptance-summary-value')
            const independent = document.querySelector('input[data-browser-independent]')
            const native = getComputedStyle(independent).borderRadius
            const original = [status, summary].map(el => el.textContent)
            const profiles = []
            for (const theme of ['light', 'dark']) for (const dir of ['ltr', 'rtl']) for (const density of [.85, 1, 1.15]) {
                document.documentElement.classList.toggle('dark', theme === 'dark')
                document.documentElement.dir = dir
                document.getElementById('installed-status').lang = 'ar'
                status.style.setProperty('--fff-field-focus-border', 'rgb(10, 20, 30)')
                status.style.setProperty('--fff-ext-acceptance-status--token-color', 'var(--fff-field-focus-border)')
                summary.style.setProperty('--fff-ext-acceptance-summary--token-color', 'rgb(40, 50, 60)')
                status.parentElement.style.setProperty('--fff-density-scale', String(density))
                status.parentElement.style.fontSize = 'calc(16px * var(--fff-density-scale))'
                summary.parentElement.style.fontSize = '16px'
                status.style.setProperty('--fff-global-radius', '12px')
                summary.style.setProperty('--fff-global-radius', '8px')
                profiles.push([getComputedStyle(status).color, getComputedStyle(summary).color,
                    getComputedStyle(status).borderRadius, getComputedStyle(summary).borderRadius,
                    getComputedStyle(independent).borderRadius === native,
                    Math.abs(parseFloat(getComputedStyle(status).fontSize) - 16 * density) < .01,
                    getComputedStyle(summary).fontSize === '16px'])
            }
            document.documentElement.classList.remove('dark'); document.documentElement.dir = 'ltr'
            return { original, profiles, after: [status, summary].map(el => el.textContent) }
        })
        assert.deepEqual(visual.after, visual.original)
        for (const profile of visual.profiles) assert.deepEqual(profile, ['rgb(10, 20, 30)', 'rgb(40, 50, 60)', '12px', '8px', true, true, true])
        assert.equal(requests.slice(start).filter(url => url.startsWith(secondPrefix) && url.endsWith('/display.css')).length, 1)
        assert.equal(requests.slice(start).filter(url => url.startsWith(secondPrefix) && url.endsWith('.js')).length, 0)
        proofs.push('second Composer family owns one CSS and no form JS; namespaced primary colors/radii and inherited author-wrapper density remain independent through light/dark and LTR/RTL; real small/large child sizing reuses the shared renderer CSS without fetching assets again or changing independent input geometry')
        await composition.evaluate(() => document.getElementById('installed-summary').remove())
        proofs.push('actual rendered D teleports to a public custom dialog and prepares on activation; disposing first A preserves second A/D and independent B, with one author module and one shared display CSS')
        await composition.evaluate(async () => {
            const html = await (await fetch('/composition-morph')).text()
            const next = new DOMParser().parseFromString(html, 'text/html').getElementById('composition-two')
            // Deferred D remains in its explicit public surface; morph only A's
            // current children, never implicitly copy the teleported renderer.
            next.querySelector('#dialog-two')?.remove()
            Alpine.morph(document.getElementById('composition-two'), next.outerHTML)
        })
        await composition.waitForFunction(() => document.querySelector('#composition-two input')?.maxLength === 3
            && document.querySelector('#composition-two [data-fff-text-input]')?._fffFactoryReady === true)
        assert.equal(await composition.evaluate(() => document.querySelector('#composition-two input').id), before.ids[1])
        assert.equal(await composition.evaluate(() => window.compositionOwners.two.child.ready), true)
        await composition.evaluate(() => {
            window.compositionOwners.two.child.close()
            window.compositionOwners.two.root.hidden = true
        })
        assert.equal(await composition.locator(`link[href*="${authorPrefix}"][href$="/display.css"]`).count(), 0)
        assert.equal(await composition.locator(`link[href*="${secondPrefix}"][href$="/display.css"]`).count(), 1)
        assert.equal(await composition.locator('input[data-browser-independent]').count(), 1)
        assert.equal(await composition.locator('input[data-browser-independent]').inputValue(), 'outside')
        proofs.push('native installed Livewire/Alpine morph replaces mapped child options while retaining stable child IDs and teleported owner; closing D releases only its CSS and preserves independent B')
        const memoryAfter = await memorySnapshot()
        if (memoryBefore && memoryAfter) {
            // One integrated scenario may retain evaluated immutable modules. The
            // separate production stress profile verifies the repeated-cycle plateau.
            assert(memoryAfter.documents <= memoryBefore.documents + 1, 'Installed composition retained an extra document')
            assert(memoryAfter.jsEventListeners <= memoryBefore.jsEventListeners + 32, 'Installed composition retained unbounded native listeners')
            assert(memoryAfter.usedBytes <= memoryBefore.usedBytes + 4 * 1024 * 1024, 'Installed composition exceeded its retained-heap envelope')
            proofs.push(`installed composition CDP envelope after import/init faults, replacement, disposal and morph: ${JSON.stringify({ before: memoryBefore, after: memoryAfter })}; repeated-cycle plateau remains the independent production stress check`)
        }
        await memorySession?.detach()
        await composition.close()

        const cdn = await browser.newPage(), cdnStart = requests.length
        cdn.on('pageerror', error => errors.push(error.message))
        const origin = `http://127.0.0.1:${server.address().port}`
        const cdnOrigin = `http://127.0.0.1:${cdnServer.address().port}`
        await cdn.goto(`${origin}/composition-cdn`)
        await cdn.evaluate(async coreUrl => {
            const { Livewire } = await import('/alpine.js')
            await import('/schemas.js')
            window.Livewire = Livewire
            window.livewireScriptConfig = { uri: '/livewire/update', csrf: 'fixture-token' }
            window.__fffAuthorFixture = { inits: {}, successes: {}, disposals: {}, fail: new Set(), pending: new Map() }
            const core = await import(coreUrl)
            core.installCoreRuntime(); Livewire.start()
        }, `${cdnOrigin}${corePrefix}flex-core-runtime.js`)
        await cdn.waitForFunction(() => document.querySelector('[data-fff-text-input]')?._fffFactoryReady === true)
        await cdn.evaluate(async () => {
            const sdk = globalThis[Symbol.for('fff.sdk.v1')]
            window.cdnOwner = await sdk.createAssetSurface(document.getElementById('composition-one'))
            await window.cdnOwner.activate()
            window.cdnDialog = await sdk.createAssetSurface(document.getElementById('dialog-one'), { parent: window.cdnOwner })
            if (await window.cdnDialog.activate()) document.getElementById('dialog-one').hidden = false
        })
        assert.equal(await cdn.evaluate(() => window.cdnDialog.ready), true)
        const cdnLinks = await cdn.locator('link[data-fff-managed-asset]').evaluateAll(links => links.map(link => link.href))
        assert.ok(cdnLinks.length > 0 && cdnLinks.every(url => url.startsWith(cdnOrigin)), 'An installed composition resource escaped its CDN origin')
        assert.equal(requests.slice(cdnStart).filter(url => new URL(url, origin).pathname === entryUrl).length, 1)
        assert.equal(await cdn.evaluate(() => window.__fffAuthorFixture.moduleEvaluations), 1)
        await cdn.evaluate(() => {
            window.previousBody = document.body
            window.navigationFinished = false
            document.addEventListener('livewire:navigated', () => { window.navigationFinished = true }, { once: true })
            Livewire.navigate('/composition-cdn?visit=second&panel=second')
        })
        await cdn.waitForFunction(() => window.navigationFinished && document.body !== window.previousBody
            && document.querySelector('[data-fff-text-input]')?._fffFactoryReady === true)
        assert.equal(await cdn.evaluate(() => window.cdnOwner.state), 'disposed')
        assert.equal(await cdn.evaluate(() => window.cdnDialog.state), 'disposed')
        assert.equal(await cdn.locator('input[data-browser-independent]').inputValue(), 'outside')
        assert.equal(await cdn.locator('#composition-one input').first().evaluate(input => input.maxLength), 4)
        assert.equal(await cdn.locator('#composition-two input').first().evaluate(input => input.maxLength), 9)
        assert.equal(await cdn.locator('html').getAttribute('dir'), 'rtl')
        const cdnAfter = await cdn.locator('link[data-fff-managed-asset]').evaluateAll(links => links.map(link => link.href))
        assert.equal(new Set(cdnAfter).size, cdnAfter.length)
        proofs.push('installed A/B/C/D initializes from a second HTTP CDN origin; native Livewire.navigate enters a second real Filament panel with different child limits and RTL, replaces body, disposes teleported owners, reconnects new B and retains exactly one link for every current asset identity')
        await cdn.close()
    }
    assert.deepEqual(errors, [])
    proofs.push('new owner remounts without repeated module request or duplicate subscriptions; no browser errors')
    console.log(JSON.stringify({ browser: await browser.version(), generatedStarter: true, installationFixture: !!installedApplication, publishedAssets: !!installedApplication, proofs, errors }))
} finally {
    await browser?.close()
    if (server) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)) }
    if (cdnServer) { cdnServer.closeAllConnections(); await new Promise(resolve => cdnServer.close(resolve)) }
    fs.rmSync(temporary, { recursive: true, force: true })
}
