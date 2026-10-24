import assert from 'node:assert/strict'
import fs from 'node:fs'
import { expect } from '@playwright/test'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'

const browser = await launchCoreBrowser(), results = []
try {
    const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
    for (const width of [1440, 390]) for (const dark of [false, true]) {
        const page = await context.newPage(), errors = [], warnings = []
        page.on('pageerror', error => errors.push(error.message))
        page.on('console', message => { if (/not an animatable color|mix.*color/i.test(message.text())) warnings.push(message.text()) })
        await page.setViewportSize({ width, height: 1000 })
        await page.goto(`https://wyachts-super-app.test/admin/field-performance/date-range?_measure=1${dark ? '&_dark=1' : ''}`)
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        for (const custom of [false, true]) {
            const field = page.locator(`[data-benchmark-field="${custom ? 'filled-1' : 'empty-1'}"]`)
            const owner = field.locator('[data-fff-civil-date]'), trigger = field.locator('[data-fff-open-trigger]')
            await trigger.scrollIntoViewIfNeeded()
            if (custom) await owner.evaluate(root => root.style.setProperty('--fff-flex-text-input-border', 'color(display-p3 .1 .3 .85)'))
            // Exercise both the unfocused shell and a real input-focus opening.
            if (custom) await trigger.locator('input').click()
            else await trigger.click({ position: { x: 12, y: 16 } })
            const material = page.locator('.fff-dt-range-material')
            await expect(material).toHaveCount(1)
            await expect(page.locator('.fff-dt-range-value-flight')).toHaveCount(0)
            // The exit must use the current source paint, not a stale opening snapshot.
            if (custom) await owner.evaluate(root => root.style.setProperty('--fff-flex-text-input-border', 'color(display-p3 .8 .2 .3)'))
            await page.keyboard.press('Escape')
            const samples = await material.evaluate(material => {
                const paint = material.getAnimations().find(effect => effect.effect.getKeyframes().some(frame => Object.hasOwn(frame, 'borderColor')))
                if (!paint || paint.effect.getKeyframes().length !== 2) throw Error('Paint is not a native CSS endpoint interpolation')
                const effects = [...document.getAnimations()].filter(effect => effect.effect.target?.closest?.('.fff-dt-range-material,.fff-dt-range-value-flight,.fff-dt-range-glyph-flight'))
                effects.forEach(effect => effect.pause())
                window.__rangePaintEffects = effects
                const duration = Number(paint.effect.getTiming().duration)
                const canvas = document.createElement('canvas'); canvas.width = canvas.height = 1
                const ctx = canvas.getContext('2d', { willReadFrequently: true })
                return [0, .01, .1, .25, .5, .75, 1].map(progress => {
                    for (const effect of effects) effect.currentTime = duration * progress
                    const style = getComputedStyle(material)
                    ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = style.borderColor; ctx.fillRect(0, 0, 1, 1)
                    return { progress, border: style.borderColor, shadow: style.boxShadow,
                        pixel: [...ctx.getImageData(0, 0, 1, 1).data], easing: paint.effect.getTiming().easing }
                })
            })
            if (custom) {
                const distance = (a, b) => Math.max(...a.slice(0, 3).map((value, index) => Math.abs(value - b[index])))
                assert.ok(distance(samples[0].pixel, samples.at(-1).pixel) > 30)
                assert.ok(samples.slice(1, -1).some(sample => distance(sample.pixel, samples[0].pixel) > 2 && distance(sample.pixel, samples.at(-1).pixel) > 2), 'Color snapped directly to its endpoint')
            }
            await page.evaluate(() => { for (const effect of window.__rangePaintEffects) effect.finish(); window.__rangePaintEffects = [] })
            await expect(material).toHaveCount(0)
            const resting = await trigger.evaluate(trigger => {
                const canvas = document.createElement('canvas'); canvas.width = canvas.height = 1
                const ctx = canvas.getContext('2d', { willReadFrequently: true })
                ctx.fillStyle = getComputedStyle(trigger).borderColor; ctx.fillRect(0, 0, 1, 1)
                return [...ctx.getImageData(0, 0, 1, 1).data]
            })
            assert.deepEqual(resting, samples.at(-1).pixel, 'Material/source border changed at handoff')
            results.push({ width, dark, custom, samples, resting })
        }
        assert.deepEqual(errors, []); assert.deepEqual(warnings, [])
        console.log(`PASS ${browserEngine}/${width}/${dark ? 'dark' : 'light'}: native mixed-color exit, live source paint and exact border handoff.`)
        await page.close()
    }
    fs.writeFileSync(process.env.FFF_RANGE_PAINT_OUTPUT ?? `/tmp/fff-range-paint-${browserEngine}.json`, JSON.stringify({ browser: browserEngine, results }, null, 2))
} finally { await browser.close() }
