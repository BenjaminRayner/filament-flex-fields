import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, expect } from '@playwright/test'

const browser = await chromium.launch(), results = []
try {
    const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
    for (const width of [1440, 390]) for (const slug of ['color', 'tags']) {
        const page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        await page.goto(`https://wyachts-super-app.test/admin/field-performance/${slug}?_measure=1`, { waitUntil: 'domcontentloaded' })
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        const variants = slug === 'color' ? ['hex', 'rgb', 'hsl', 'rgba', 'palette', 'grid'] : ['suggestions']
        for (const variant of variants) {
            const field = page.locator(`[data-benchmark-field="${variant}-1"]`)
            await field.scrollIntoViewIfNeeded()
            const samples = []
            for (let run = 0; run < 2; run++) {
                samples.push(await field.evaluate((field, slug) => new Promise((resolve, reject) => {
                    const trigger = field.querySelector(slug === 'color' ? '.fff-color-trigger' : 'input[role="combobox"]')
                    const start = performance.now(), deadline = start + 5000
                    const frame = () => {
                        const selector = slug === 'color' ? '.fff-color-panel' : '.fff-select-headless-menu'
                        const panel = [...document.querySelectorAll(selector)].find(el => {
                            const style = getComputedStyle(el)
                            return el.getBoundingClientRect().height > 0 && style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) > .02
                        })
                        if (panel) resolve(performance.now() - start)
                        else if (performance.now() > deadline) reject(Error('First-intent presentation missed deadline'))
                        else requestAnimationFrame(frame)
                    }
                    if (slug === 'color') trigger.click()
                    else trigger.focus()
                    requestAnimationFrame(frame)
                }), slug))
                await page.keyboard.press('Escape')
                await page.locator('h1').first().click()
                await expect(page.locator('.fff-color-panel:visible, .fff-select-headless-menu:visible')).toHaveCount(0)
            }
            results.push({ width, slug, variant, fields: 300, firstVisibleMs: samples[0], repeatVisibleMs: samples[1] })
        }
        if (slug === 'color') {
            const animatedOwner = page.locator('[data-benchmark-field="hex-1"] .fff-flex-color-picker')
            await animatedOwner.evaluate(el => { window.Alpine.$data(el).animationDuration = 900 })
            await animatedOwner.locator('.fff-color-trigger').click()
            await expect(page.locator('.fff-color-panel:visible')).toHaveCount(1)
            await page.locator('.fff-color-panel:visible').evaluate(async el => { await Promise.allSettled(el.getAnimations().map(animation => animation.finished)) })
            await page.keyboard.press('Escape')
            await page.waitForFunction(() => [...document.querySelectorAll('.fff-color-panel')].some(el => getComputedStyle(el).opacity > .05 && getComputedStyle(el).opacity < .95 && el.getAnimations().some(animation => animation.playState === 'running')))
            assert.equal(await page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces), 1, 'Color dependencies released before the actual exit animation finished')
            await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
            await expect.poll(() => page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces)).toBe(0)
            await animatedOwner.locator('.fff-color-trigger').click()
            await expect(page.locator('.fff-color-panel:visible')).toHaveCount(1)
            await expect.poll(() => page.locator('.fff-color-panel:visible').evaluate(el => Number(getComputedStyle(el).opacity))).toBeGreaterThan(.95)
            await page.keyboard.press('Escape')
            await page.waitForFunction(() => [...document.querySelectorAll('.fff-color-panel')].some(el => getComputedStyle(el).opacity > .05 && getComputedStyle(el).opacity < .95 && el.getAnimations().some(animation => animation.playState === 'running')))
            await animatedOwner.locator('.fff-color-trigger').click()
            await expect.poll(() => page.locator('.fff-color-panel:visible').evaluate(el => Number(getComputedStyle(el).opacity))).toBeGreaterThan(.95)
            assert.equal(await page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces), 1, 'Interrupted close released the reopened owner')
            await page.keyboard.press('Escape')
            await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
            await expect.poll(() => page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces)).toBe(0)
            // Destruction while a real preparation barrier is held cannot reveal
            // an orphaned panel or retain a registered custom surface.
            await page.evaluate(() => {
                const api = window.FffAssetInjector, original = api.registerAssetSurface
                api.registerAssetSurface = (...args) => {
                    const surface = original(...args)
                    return { dispose: () => surface.dispose(), activate: async () => {
                        const ready = await surface.activate()
                        window.__colorDisposalHeld = true
                        await new Promise(resolve => { window.__releaseDisposedColor = resolve })
                        return ready
                    } }
                }
            })
            const owner = page.locator('[data-benchmark-field="hex-50"] .fff-flex-color-picker')
            await owner.locator('.fff-color-trigger').click()
            await page.waitForFunction(() => window.__colorDisposalHeld)
            await owner.evaluate(el => { window.Alpine.destroyTree(el); el.remove(); window.__releaseDisposedColor() })
            await expect.poll(() => page.evaluate(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces)).toBe(0)
            await expect(page.locator('.fff-color-panel:visible')).toHaveCount(0)
        }
        assert.deepEqual(errors, [])
        console.log(`PASS ${width}/${slug}: first/repeated frame visibility, no page errors; Color exit retention, interrupted-close reopen and pending-owner disposal pass.`)
        await page.close()
    }
    fs.writeFileSync(process.env.FFF_FIRST_INTENT_OUTPUT ?? '/tmp/fff-picker-first-intent.json', JSON.stringify({ method: 'Actual local 300-field page; in-page DOM click/focus to first RAF with visible presentation opacity >0.02; includes opening animation, excludes Playwright polling/scroll time.', results }, null, 2))
} finally { await browser.close() }
