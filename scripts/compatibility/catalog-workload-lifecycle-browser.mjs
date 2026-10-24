import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, webkit, expect } from '@playwright/test'
const engine = process.env.FFF_BROWSER ?? 'chromium'
const browser = await ({ chromium, webkit })[engine].launch(), results = []
try {
    const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
    if (process.env.FFF_DEBUG_RESIZE) await context.addInitScript(() => {
        const Native = window.ResizeObserver
        window.__resizeDeliveries = []
        window.ResizeObserver = class extends Native {
            constructor(callback) {
                const registration = new Error().stack
                super((entries, observer) => {
                    window.__resizeDeliveries.push({ time: performance.now(), registration, targets: entries.map(e => e.target.className) })
                    if (window.__resizeDeliveries.length > 20) window.__resizeDeliveries.shift()
                    callback(entries, observer)
                })
            }
        }
        window.addEventListener('error', event => { if (event.message.includes('ResizeObserver')) console.log('RESIZE_DIAGNOSTIC', JSON.stringify(window.__resizeDeliveries)) })
    })
    for (const width of [1440, 390]) {
        const page = await context.newPage(), errors = []
        if (process.env.FFF_DEBUG_RESIZE) page.on('console', message => { if (message.text().startsWith('RESIZE_DIAGNOSTIC')) console.log(message.text()) })
        page.on('pageerror', async error => { errors.push(error.message); console.log('PAGE_ERROR', width, error.message, await page.evaluate(() => window.__catalogPhase).catch(() => 'closed')) })
        await page.setViewportSize({ width, height: 1000 })
        let release, requested
        const held = new Promise(resolve => { release = resolve }), request = new Promise(resolve => { requested = resolve })
        await page.route(/\/components\/(select-field|phone-field|font-select|icon-picker-field)\.js$/, async route => { requested(); await held; await route.continue() })
        await page.goto('https://wyachts-super-app.test/admin/field-performance/catalogs?_measure=1', { waitUntil: 'domcontentloaded' })
        await request
        await page.evaluate(() => document.fonts.ready)
        const boxes = () => page.locator('[data-benchmark-field]').evaluateAll(fields => fields.map(field => {
            const control = field.querySelector('.fff-select-field__shell, .fff-phone-field__shell, .fff-currency-field__shell, .fff-font-select, .fff-icon-select')
            return { id: field.dataset.benchmarkField, height: control.getBoundingClientRect().height }
        }))
        const before = await boxes()
        release()
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        const after = await boxes()
        assert.equal(after.length, 300)
        assert.deepEqual(after, before, 'Initial control heights changed after factory preparation')
        assert.equal(await page.locator('.fff-select-headless-menu').count(), 0)
        const openings = []
        for (const variant of ['country', 'phone', 'timezone', 'currency', 'icon', 'font']) {
            await page.evaluate(variant => { window.__catalogPhase = variant }, variant)
            const field = page.locator(`[data-benchmark-field="${variant}-1"]`), trigger = field.locator('[data-fff-open-trigger]').first()
            await trigger.scrollIntoViewIfNeeded()
            const samples = []
            for (let run = 0; run < 2; run++) {
                samples.push(await trigger.evaluate(trigger => new Promise((resolve, reject) => {
                    const start = performance.now()
                    const check = () => {
                        const menu = [...document.querySelectorAll('.fff-select-headless-menu')].find(el => {
                            const style = getComputedStyle(el)
                            return el.getBoundingClientRect().height > 0 && style.visibility !== 'hidden' && style.display !== 'none' && Number(style.opacity) > .02
                        })
                        if (menu) resolve(performance.now() - start)
                        else if (performance.now() - start > 5000) reject(Error('Opening missed deadline'))
                        else requestAnimationFrame(check)
                    }
                    trigger.click(); requestAnimationFrame(check)
                })))
                const menu = page.locator('.fff-select-headless-menu:visible')
                await expect(menu).toHaveCount(1)
                await expect(menu.locator('[role="listbox"]')).toHaveAttribute('id', /.+/)
                await page.keyboard.press('Escape')
                await page.locator('h1').first().click()
                await expect(page.locator('.fff-select-headless-menu:visible')).toHaveCount(0)
            }
            openings.push({ variant, firstVisibleMs: samples[0], repeatVisibleMs: samples[1] })
        }
        await page.evaluate(() => { window.__catalogPhase = 'selection-and-commit' })
        // Selection/search with the new transports; then a real native morph.
        const field = page.locator('[data-benchmark-field="country-1"]'), neighbor = page.locator('[data-benchmark-field="country-2"]')
        const labelBefore = await neighbor.locator('[data-fff-open-trigger]').innerText()
        await field.locator('[data-fff-open-trigger]').click()
        const menu = page.locator('.fff-select-headless-menu:visible')
        await menu.locator('input[type="search"]').fill('Germany')
        await menu.getByRole('option').filter({ hasText: 'Germany' }).first().click()
        await expect(field.locator('[data-fff-open-trigger]')).toContainText('Germany')
        assert.equal(await neighbor.locator('[data-fff-open-trigger]').innerText(), labelBefore)
        await page.evaluate(() => { window.__catalogOwners = [...document.querySelectorAll('[x-fff-load-src]')] })
        await page.evaluate(async () => {
            const root = document.querySelector('[data-benchmark-form]').closest('[wire\\:id]')
            await Livewire.find(root.getAttribute('wire:id')).$commit()
        })
        await expect(field.locator('[data-fff-open-trigger]')).toContainText('Germany')
        assert.equal(await page.evaluate(() => window.__catalogOwners.every(el => el.isConnected)), true, 'State commit remounted existing owners')
        await field.locator('[data-fff-open-trigger]').click()
        await expect(page.locator('.fff-select-headless-menu:visible')).toHaveCount(1)
        await page.keyboard.press('Escape'); await page.locator('h1').first().click()
        await page.unroute(/\/components\/(select-field|phone-field|font-select|icon-picker-field)\.js$/)
        await page.reload({ waitUntil: 'domcontentloaded' })
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        assert.deepEqual(await boxes(), after, 'Reload changed control height')
        assert.equal(await page.locator('.fff-select-headless-menu').count(), 0)
        assert.deepEqual(errors, [])
        results.push({ width, fields: 300, factories: 400, initialMenus: 0, stableHeldInitAndReload: true, independentSelection: true, nativeMorphRetainsOwners: true, openings, errors })
        console.log(`PASS ${engine}/${width}: all300 fields/400factories, zero initial menus, stable held-init/reload, six first/repeated opens, independent selection and native morph.`)
        await page.close()
    }
    fs.writeFileSync(process.env.FFF_CATALOG_LIFECYCLE_OUTPUT ?? `/tmp/fff-catalog-lifecycle-${engine}.json`, JSON.stringify({ engine, results }, null, 2))
} finally { await browser.close() }
