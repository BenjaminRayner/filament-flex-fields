import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, webkit, expect } from '@playwright/test'

const engine = process.env.FFF_SLIDER_BROWSER ?? 'chromium'
const browser = await ({ chromium, webkit }[engine]).launch()
const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
const results = []
try {
    for (const width of [1440, 390]) {
        const page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        await page.goto('https://wyachts-super-app.test/admin/field-performance/slider?_measure=1', { waitUntil: 'domcontentloaded' })
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt, null, { timeout: 30000 })
        assert.equal(await page.locator('[data-benchmark-field]').count(), 300)
        assert.equal(await page.locator('[data-slider-ready]').count(), 300)
        for (const variant of ['basic', 'range', 'decimal', 'elastic', 'marks']) for (const index of [1, 50]) {
            const field = page.locator(`[data-benchmark-field="${variant}-${index}"]`)
            await field.scrollIntoViewIfNeeded()
            await field.evaluate(root => {
                root.__displayProbe = { updates: [], commits: [] }
                root.addEventListener('number-flow-update', event => root.__displayProbe.updates.push({
                    value: event.target.value, format: event.target.format, animated: event.target.animated,
                }))
                root.addEventListener('fff-slider-commit', event => root.__displayProbe.commits.push(event.detail))
            })
            const thumb = field.getByRole('slider').first(), rail = field.locator('[x-ref="track"]')
            const from = await thumb.boundingBox(), track = await rail.boundingBox()
            await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2)
            await page.mouse.down()
            await page.mouse.move(track.x + track.width * .634789123, from.y + from.height / 2, { steps: 17 })
            await page.mouse.up()
            const snapshot = await field.evaluate(root => ({
                probe: root.__displayProbe,
                value: Number(root.querySelector('[role="slider"]').getAttribute('aria-valuenow')),
                flows: [...root.querySelectorAll('number-flow')].map(flow => ({ value: flow.value, format: flow.format, timing: flow.transformTiming.duration })),
            }))
            const precision = variant === 'decimal' ? 1 : 0
            assert(snapshot.probe.updates.length > 0)
            assert.equal(snapshot.probe.commits.length, 1, 'a drag commits exactly once')
            for (const update of snapshot.probe.updates) {
                assert.equal(update.format.maximumFractionDigits, precision)
                assert.equal(update.value, Number(update.value.toFixed(precision)))
            }
            assert(snapshot.probe.updates.some(update => update.animated), 'rolling animation remains enabled')
            assert(snapshot.flows.every(flow => flow.timing === 380))
            if (variant === 'basic') {
                assert(!Number.isInteger(snapshot.value), 'continuous model precision remains intact')
                assert.equal(snapshot.flows[0].value, Math.round(snapshot.value))
            }
            if (variant === 'decimal') assert.equal(snapshot.value * 2, Math.round(snapshot.value * 2))
            await thumb.press('Home'); await expect(thumb).toHaveAttribute('aria-valuenow', '0')
            if (variant !== 'range') { await thumb.press('End'); await expect(thumb).toHaveAttribute('aria-valuenow', '100') }
            results.push({ engine, width, variant, index, ...snapshot })
        }
        const id = await page.locator('[data-benchmark-form]').evaluate(el => el.closest('[' + CSS.escape('wire:id') + ']').getAttribute('wire:id'))
        const before = await page.getByRole('slider').evaluateAll(elements => elements.map(el => el.getAttribute('aria-valuenow')))
        await page.evaluate(id => window.Livewire.find(id).$commit(), id)
        assert.deepEqual(await page.getByRole('slider').evaluateAll(elements => elements.map(el => el.getAttribute('aria-valuenow'))), before)
        assert.equal(await page.locator('[data-slider-ready]').count(), 300)
        assert.deepEqual(errors, [])
        await page.emulateMedia({ reducedMotion: 'reduce' })
        const basic = page.locator('[data-benchmark-field="basic-1"]')
        await basic.getByRole('slider').press('Home')
        assert.equal(await basic.locator('number-flow').evaluate(el => el.animated), false)
        await page.close()
    }
    fs.writeFileSync(process.env.FFF_SLIDER_OUTPUT ?? '/tmp/fff-slider-display-browser.json', JSON.stringify(results, null, 2))
    console.log(`PASS: ${engine}, ${results.length} first/last drag cases; 300 ready owners at 1440/390px, animation, decimal steps, continuous model, keyboard, Livewire and reduced motion.`)
} finally { await browser.close() }
