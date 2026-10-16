import assert from 'node:assert/strict'
import { build } from 'esbuild'
import { createServer } from 'node:http'
import { launchCoreBrowser } from './browser-profile.mjs'
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { resolve } from 'node:path'
import { execFileSync } from 'node:child_process'
import { verifyCssSourceContract } from '../assets/css-source-contract.mjs'

await verifyCssSourceContract()
const isolationDirectory = await mkdtemp(resolve(tmpdir(), 'fff-css-source-isolation-'))
try {
    const cli = resolve('node_modules/@tailwindcss/cli/dist/index.mjs')
    const input = resolve('resources/css/core.css')
    const output = resolve(isolationDirectory, 'core.css')
    const compile = () => execFileSync(process.execPath, [cli, '-i', input, '-o', output, '--minify'], {
        cwd: isolationDirectory, stdio: 'pipe', timeout: 30000,
    })
    compile()
    const before = await readFile(output)
    for (const directory of ['docs', 'scripts', 'tests', 'vendor', 'resources/dist']) {
        await mkdir(resolve(isolationDirectory, directory), { recursive: true })
        await writeFile(resolve(isolationDirectory, directory, 'sample.html'), '<div class="m-[9137px] p-[9139px] text-[9141px]"></div>')
    }
    compile()
    assert.deepEqual(await readFile(output), before, 'Unrelated cwd/documentation/tooling must not change compiled production CSS bytes.')
    assert.deepEqual(before, await readFile('resources/dist/css/core.css'), 'Build must be reproducible from another working directory.')
} finally { await rm(isolationDirectory, { recursive: true, force: true }) }

const compiled = await build({ stdin: { resolveDir: process.cwd(), contents: `
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js';
import { createStylesheetOrder } from './resources/js/runtime/assets/stylesheet-order.js';
window.createStore = () => createAssetLinkStore({ document, window });
window.order = () => createStylesheetOrder(document);
` }, bundle: true, write: false, format: 'iife', platform: 'browser' })
const resources = {
    base: '.cascade-image { --shared: 1; color: rgb(1, 2, 3) }',
    a: '.cascade-image { color: rgb(10, 20, 30) }',
    b: '.cascade-image { color: rgb(40, 50, 60) }',
}
resources['foundation-core'] = await readFile('resources/dist/css/core.css', 'utf8')
resources['foundation-catalog'] = await readFile('resources/dist/css/playground.css', 'utf8')
const baselinePath = process.argv.find(argument => argument.startsWith('--baseline-playground='))?.slice('--baseline-playground='.length)
if (baselinePath) resources['foundation-legacy-catalog'] = await readFile(baselinePath, 'utf8')
const baselineCore = process.argv.find(argument => argument.startsWith('--baseline-core='))?.slice('--baseline-core='.length)
if (baselineCore) resources['foundation-legacy-core'] = await readFile(baselineCore, 'utf8')
assert.ok(resources['foundation-core'].includes('--fff-density-scale'), 'Shared tokens must have their core owner.')
assert.ok(!resources['foundation-catalog'].includes('--fff-density-scale'), 'Catalog must not redeclare shared core tokens.')
assert.ok(!resources['foundation-catalog'].includes('@layer theme'), 'Catalog must not duplicate the Tailwind theme.')
const requests = new Map(), proofs = ['explicit renderer-only source closure; identical compiled bytes across cwd and unrelated source pollution'], errors = []
const server = createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost')
    if (url.pathname === '/missing-author-font.woff2') { response.writeHead(404); response.end(); return }
    if (url.pathname === '/runtime.js') { response.setHeader('content-type', 'text/javascript'); response.end(compiled.outputFiles[0].text); return }
    if (url.pathname.endsWith('.css')) {
        const name = url.pathname.slice(1, -4)
        requests.set(name, (requests.get(name) ?? 0) + 1)
        response.setHeader('content-type', 'text/css')
        setTimeout(() => response.end(resources[name] ?? ''), Number(url.searchParams.get('delay') ?? 0))
        return
    }
    response.setHeader('content-type', 'text/html')
    const delay = url.searchParams.get('reverse') === 'true' ? { a: 5, b: 60 } : { a: 60, b: 5 }
    const urls = ['base', 'a', 'b'].map(name => `/${name}.css?delay=${delay[name] ?? 0}`)
    const metadata = JSON.stringify({ format: 1, stylesheets: urls }).replaceAll('"', '&quot;')
    response.end(`<!doctype html><html><head><template data-fff-style-order="${metadata}"></template>
        <style id="native-before">.native { color: rgb(70,80,90) }</style>
        <script src="/runtime.js"></script></head><body><div class="cascade-image">Image</div><div class="native">Native</div></body></html>`)
})
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
const origin = `http://127.0.0.1:${server.address().port}`
const browser = await launchCoreBrowser({ headless: true })
const page = await browser.newPage()
page.on('pageerror', error => errors.push(error.message))
try {
    for (const reverse of [false, true]) for (const activation of ['ab', 'ba', 'concurrent']) {
        requests.clear()
        await page.goto(`${origin}/?reverse=${reverse}`)
        const result = await page.evaluate(async activation => {
            const urls = JSON.parse(document.querySelector('template').dataset.fffStyleOrder).stylesheets
            const [base, a, b] = urls
            const first = window.createStore(), second = window.createStore()
            window.clients = [first, second]
            if (activation === 'concurrent') await Promise.all([second.load(b, 'stylesheet'), first.load(a, 'stylesheet'), first.load(base, 'stylesheet'), second.load(base, 'stylesheet')])
            else for (const url of (activation === 'ab' ? [base, a, b] : [b, a, base])) await first.load(url, 'stylesheet')
            const native = document.createElement('style')
            native.id = 'native-after'; native.textContent = '.native { color: rgb(91,92,93) }'; document.head.appendChild(native)
            first.dedupe()
            return { order: [...document.querySelectorAll('link[rel="stylesheet"]')].map(link => new URL(link.href).pathname),
                color: getComputedStyle(document.querySelector('.cascade-image')).color,
                native: getComputedStyle(document.querySelector('.native')).color,
                nativeOrder: [...document.querySelectorAll('style')].map(style => style.id) }
        }, activation)
        assert.deepEqual(result.order, ['/base.css', '/a.css', '/b.css'])
        assert.equal(result.color, 'rgb(40, 50, 60)')
        assert.equal(result.native, 'rgb(91, 92, 93)')
        assert.deepEqual(result.nativeOrder, ['native-before', 'native-after'])
        assert.deepEqual([...requests].sort(), [['a', 1], ['b', 1], ['base', 1]])
        proofs.push(`${activation}, response reverse=${reverse}: stable cascade, one shared request, application links unchanged`)
    }
    await page.evaluate(() => { window.clients[0].dispose() })
    const reopen = await page.evaluate(async () => {
        const client = window.clients[1]
        const links = [...document.querySelectorAll('link[rel="stylesheet"]')]
        const a = links[1].href
        links[1].remove(); client.forget(a, 'stylesheet')
        await client.load(a, 'stylesheet')
        const counts = [...document.querySelectorAll('link[rel="stylesheet"]')].map(link => new URL(link.href).pathname)
        client.dispose()
        return counts
    })
    assert.deepEqual(reopen, ['/base.css', '/a.css', '/b.css'])
    proofs.push('one client disposal preserves shared engine; removed CSS reacquires its original cascade position')
    // Already loaded SSR links are adopted without replacing/fetching them.
    await page.goto(origin)
    const ssr = await page.evaluate(async () => {
        const urls = JSON.parse(document.querySelector('template').dataset.fffStyleOrder).stylesheets
        const links = []
        for (const url of [urls[2], urls[0], urls[1]]) {
            const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = url; link.dataset.fffStylesheet = 'true'
            await new Promise((resolve, reject) => { link.onload = resolve; link.onerror = reject; document.head.appendChild(link) }); links.push(link)
        }
        const store = window.createStore()
        await Promise.all(urls.map(url => store.load(url, 'stylesheet')))
        const result = { order: [...document.querySelectorAll('link')].map(link => new URL(link.href).pathname), same: links.every(link => link.isConnected) }
        store.dispose(); return result
    })
    assert.deepEqual(ssr, { order: ['/base.css', '/a.css', '/b.css'], same: true })
    proofs.push('oppositely ordered loaded SSR styles normalize before readiness; original nodes remain adopted')
    const admission = await page.evaluate(() => {
        const marker = document.querySelector('template')
        const original = marker.dataset.fffStyleOrder
        const rejected = []
        const cases = ['{', JSON.stringify({ format: 1, stylesheets: ['/a.css', '/a.css'] }),
            JSON.stringify({ format: 1, stylesheets: ['javascript:alert(1)'] }),
            JSON.stringify({ format: 1, stylesheets: Array(2049).fill('/a.css') }),
            JSON.stringify({ format: 1, stylesheets: ['/b.css', '/a.css'] })]
        for (const value of cases) {
            marker.dataset.fffStyleOrder = value
            try { window.createStore().dispose(); rejected.push(false) } catch (error) { rejected.push(error.code === 'asset_policy_failed') }
        }
        marker.dataset.fffStyleOrder = original
        window.createStore().dispose()
        return rejected
    })
    assert.deepEqual(admission, Array(5).fill(true))
    proofs.push('malformed, duplicate, unsafe, over-budget and incompatible catalog fail before transport; restored valid catalog retries')
    await page.goto(origin)
    const discovery = await page.evaluate(async () => {
        const store = window.createStore()
        const urls = JSON.parse(document.querySelector('template').dataset.fffStyleOrder).stylesheets
        const b = document.createElement('link'); b.rel = 'stylesheet'; b.href = urls[2]; b.dataset.fffStylesheet = 'true'
        document.body.appendChild(b)
        store.discover(document.body); store.dedupe()
        await store.load(urls[0], 'stylesheet'); await store.load(urls[1], 'stylesheet'); await store.load(urls[2], 'stylesheet')
        const result = [...document.querySelectorAll('link')].map(link => new URL(link.href).pathname)
        store.resync(); store.dispose(); return result
    })
    assert.deepEqual(discovery, ['/base.css', '/a.css', '/b.css'])
    proofs.push('body/fragment link discovery moves only owned CSS to head, then resync preserves canonical order')
    await page.goto(origin)
    const insertionFailure = await page.evaluate(async () => {
        const store = window.createStore()
        const urls = JSON.parse(document.querySelector('template').dataset.fffStyleOrder).stylesheets
        await store.load(urls[0], 'stylesheet')
        const insert = document.head.insertBefore
        document.head.insertBefore = () => { throw Error('Controlled DOM insertion failure') }
        let rejected = false
        try { await store.load(urls[1], 'stylesheet') } catch { rejected = true }
        document.head.insertBefore = insert
        const failed = store.snapshot().some(record => record.state === 'loading' || record.state === 'failed')
        await Promise.all([store.load(urls[1], 'stylesheet'), store.load(urls[2], 'stylesheet')])
        const result = { rejected, failed, order: [...document.querySelectorAll('link')].map(link => new URL(link.href).pathname) }
        store.dispose(); return result
    })
    assert.deepEqual(insertionFailure, { rejected: true, failed: false, order: ['/base.css', '/a.css', '/b.css'] })
    proofs.push('DOM insertion failure settles listeners/timer/record immediately; retry and independent owner remain functional')

    const samples = `<main id="foundation-fixture" class="grid gap-4">
        <div class="fff-playground-toolbar"><div class="fff-playground-toolbar__info">
            <h2 class="fff-playground-toolbar__title">Components</h2>
            <p class="fff-playground-toolbar__text">Your component catalog</p></div>
            <div class="fff-playground-toolbar__actions"><kbd class="fff-playground-kbd">K</kbd></div></div>
        <div class="grid md:grid-cols-2 gap-4"><div class="flex text-sm text-gray-500 dark:text-gray-400">
            <span class="truncate">A long example title that should retain its wrapping and typography</span><span class="rounded-lg">UTC</span></div></div>
        <div class="fff-playground-related-hubs"><div class="fff-playground-related-hubs__title">Related fields</div>
            <div class="fff-playground-related-hubs__list"><a class="fff-playground-related-hubs__link" href="#">
                <span class="fff-playground-related-hubs__link-label">Date picker</span>
                <span class="fff-playground-related-hubs__link-slug">date-picker</span></a></div></div>
        <div class="fff-playground-variants fff-playground-variants--stack"><div class="fi-fo-field"><div class="fi-fo-field-content-col">Sample field</div></div></div>
    </main>`
    async function foundationSnapshot(catalog, theme, dir, viewport, core = 'foundation-core') {
        await page.setViewportSize(viewport)
        await page.goto(origin)
        return page.evaluate(async ({ origin, catalog, core, theme, dir, samples }) => {
            document.documentElement.classList.toggle('dark', theme === 'dark')
            document.documentElement.dir = dir
            document.body.innerHTML = samples
            const staticComparison = document.createElement('style')
            staticComparison.textContent = '#foundation-fixture, #foundation-fixture * { transition: none !important; animation: none !important }'
            document.head.appendChild(staticComparison)
            for (const name of [core, catalog]) {
                const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = `${origin}/${name}.css`
                await new Promise((resolve, reject) => { link.onload = resolve; link.onerror = reject; document.head.appendChild(link) })
            }
            const properties = ['display', 'color', 'backgroundColor', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight',
                'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight', 'gap', 'borderRadius', 'borderWidth',
                'gridTemplateColumns', 'direction', 'whiteSpace', 'overflow', 'width', 'height']
            return [...document.querySelectorAll('#foundation-fixture, #foundation-fixture *')].map(element => {
                const style = getComputedStyle(element)
                return Object.fromEntries(properties.map(property => [property, style[property]]))
            })
        }, { origin, catalog, core, theme, dir, samples })
    }
    for (const theme of ['light', 'dark']) for (const dir of ['ltr', 'rtl']) for (const viewport of [{ width: 390, height: 844 }, { width: 1280, height: 900 }]) {
        const current = await foundationSnapshot('foundation-catalog', theme, dir, viewport)
        assert.equal(current[0].display, 'grid', 'Catalog retains core utility classes.')
        assert.equal(current[0].gap, '16px', 'Shared spacing remains available.')
        assert.ok(current.every(style => style.direction === dir), 'Direction must remain inherited.')
        if (baselinePath) {
            const before = await foundationSnapshot('foundation-legacy-catalog', theme, dir, viewport, baselineCore ? 'foundation-legacy-core' : 'foundation-core')
            assert.deepEqual(current, before, `${theme}/${dir}/${viewport.width}: shared-owner extraction changed computed layout or style.`)
        }
        proofs.push(`compiled core/catalog shared tokens/utilities: ${theme}/${dir}/${viewport.width}${baselinePath ? ', identical to pre-extraction computed styles' : ''}`)
    }
    await page.goto(origin)
    const fontFailure = await page.evaluate(async () => {
        const native = document.querySelector('.native'), before = getComputedStyle(native).color
        const font = new FontFace('fff-ext-author--font-fallback', 'url(/missing-author-font.woff2)')
        document.fonts.add(font)
        document.querySelector('.cascade-image').style.fontFamily = 'fff-ext-author--font-fallback, sans-serif'
        let failed = false
        try { await font.load() } catch { failed = true }
        return [failed, document.querySelector('.cascade-image').getBoundingClientRect().height > 0,
            native.textContent, getComputedStyle(native).color === before]
    })
    assert.deepEqual(fontFailure, [true, true, 'Native', true])
    proofs.push('missing namespaced optional font rejects its own load while fallback text remains visible and native content/style stays unchanged')
    assert.deepEqual(errors, [])
    process.stdout.write(JSON.stringify({ pass: true, groups: proofs.length, proofs }, null, 2) + '\n')
} finally { await browser.close(); await new Promise(resolve => server.close(resolve)) }
