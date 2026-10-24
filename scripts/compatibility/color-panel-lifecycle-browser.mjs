import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, webkit, expect } from '@playwright/test'

const engine = process.env.FFF_BROWSER ?? 'chromium'
const browser = await ({ chromium, webkit })[engine].launch()
const results = []
try {
    const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
    for (const width of [1440, 390]) {
        const page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        let release, requested
        const held = new Promise(resolve => { release = resolve }), request = new Promise(resolve => { requested = resolve })
        await page.route('**/components/flex-color-picker.js', async route => { requested(); await held; await route.continue() })
        await page.goto('https://wyachts-super-app.test/admin/field-performance/color?_measure=1', { waitUntil: 'domcontentloaded' })
        await request
        await page.evaluate(() => document.fonts.ready)
        const geometry = () => page.locator('.fff-color-trigger').evaluateAll(els => els.map(el => el.getBoundingClientRect().height))
        const before = await geometry()
        assert.equal(before.length, 300)
        release()
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        const after = await geometry()
        assert.ok(after.every((height, index) => Math.abs(height - before[index]) < .1), 'Trigger height jumped during initialization')
        assert.equal(await page.locator('.fff-color-panel').count(), 0)
        assert.equal(await page.locator('.fff-flex-color-picker[data-fff-alpine-ready]').count(), 300)
        const first = page.locator('[data-benchmark-field="hex-1"] .fff-flex-color-picker')
        const second = page.locator('[data-benchmark-field="rgb-1"] .fff-flex-color-picker')
        // Hold the real resource/factory barrier: a pending panel must stay hidden,
        // then lose its opening intent when another independent field is clicked.
        await page.evaluate(() => {
            const api = window.FffAssetInjector, original = api.registerAssetSurface
            let first = true
            window.__releaseColorBarrier = null
            api.registerAssetSurface = (...args) => {
                const surface = original(...args)
                if (!first) return surface
                first = false
                return { dispose: () => surface.dispose(), activate: async () => {
                    const ready = await surface.activate()
                    window.__colorBarrierReached = true
                    await new Promise(resolve => { window.__releaseColorBarrier = resolve })
                    return ready
                } }
            }
        })
        await first.locator('.fff-color-trigger').click()
        await page.waitForFunction(() => window.__colorBarrierReached)
        await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
        await second.locator('.fff-color-trigger').click()
        await expect(page.locator('.fff-color-panel:visible')).toHaveCount(1)
        await page.evaluate(() => window.__releaseColorBarrier())
        await expect.poll(() => first.evaluate(el => window.Alpine.$data(el).panelOpen)).toBe(false)
        await expect.poll(() => second.evaluate(el => window.Alpine.$data(el).panelOpen)).toBe(true)
        await page.keyboard.press('Escape')
        await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
        await expect.poll(() => page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces)).toBe(0)
        // Reopen/close the same mounted owner without duplicating DOM or handles.
        for (let i = 0; i < 3; i++) {
            await first.locator('.fff-color-trigger').click()
            await expect(page.locator('.fff-color-panel:visible')).toHaveCount(1)
            await page.keyboard.press('Escape')
            await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
        }
        await expect.poll(() => page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces)).toBe(0)
        // Actual failed preparation exposes local retry, without revealing unready HTML.
        await page.evaluate(() => {
            const api = window.FffAssetInjector, original = api.registerAssetSurface
            let once = true
            api.registerAssetSurface = (...args) => {
                const surface = original(...args)
                if (!once) return surface
                once = false
                return { dispose: () => surface.dispose(), activate: () => Promise.reject(Error('Controlled preparation failure')) }
            }
        })
        const third = page.locator('[data-benchmark-field="hsl-1"] .fff-flex-color-picker')
        await third.locator('.fff-color-trigger').click()
        const retry = third.locator(':scope > .fff-color-error button')
        await expect(retry).toBeVisible()
        await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
        await retry.click()
        await expect(page.locator('.fff-color-panel:visible')).toHaveCount(1)
        await page.keyboard.press('Escape')
        await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
        await expect.poll(() => page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces)).toBe(0)
        await page.unroute('**/components/flex-color-picker.js')
        await page.reload({ waitUntil: 'domcontentloaded' })
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        assert.deepEqual(await geometry(), after)
        assert.deepEqual(errors, [])
        results.push({ width, fields: 300, stableTriggerHeight: true, initiallyMountedPanels: 0, heldBarrier: true, latestIntentOnly: true, repeatedOpen: true, failureRetry: true, handlesAfterClose: 0, errors })
        await page.close()
        console.log(`PASS: ${engine}/${width}: 300 ready triggers, stable reload height, hidden pending panel, opening arbitration, reopen, retry and released handles.`)
    }
    // Native modal/focus boundary and eagerly rendered inline control.
    const page = await context.newPage()
    await page.goto('https://wyachts-super-app.test/admin/flex-fields/flex-color-picker', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.fff-color-inline .fff-color-panel').first()).toBeVisible()
    await page.getByRole('button', { name: 'Open shared dropdowns modal' }).click()
    const modal = page.locator('.fi-modal').filter({ has: page.getByRole('heading', { name: 'Shared dropdowns · modal preview', exact: true }) })
    const modalWindow = modal.locator('.fi-modal-window')
    await expect(modalWindow).toBeVisible({ timeout: 15000 })
    await modal.locator('.fff-color-trigger').first().click()
    const panel = modal.locator('.fff-color-panel:visible')
    await expect(panel).toHaveCount(1)
    await panel.locator('[data-fff-open-trigger]').click()
    await expect(modal.locator('.fff-select-headless-menu:visible')).toHaveCount(1)
    await page.keyboard.press('Escape')
    await expect(modal.locator('.fff-select-headless-menu:visible')).toHaveCount(0)
    await expect.poll(() => panel.evaluate(el => el.contains(el.ownerDocument.activeElement))).toBe(true)
    await page.keyboard.press('Escape')
    await expect(panel).toHaveCount(0)
    await expect(modalWindow).toBeVisible()
    await modal.getByRole('button', { name: 'Close', exact: true }).last().click()
    await expect(modalWindow).toBeHidden()
    await expect.poll(() => page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces)).toBe(0)
    await page.close()
    fs.writeFileSync(process.env.FFF_COLOR_LIFECYCLE_OUTPUT ?? `/tmp/fff-color-lifecycle-${engine}.json`, JSON.stringify({ engine, results, inlineAndNativeModal: true }, null, 2))
} finally { await browser.close() }
