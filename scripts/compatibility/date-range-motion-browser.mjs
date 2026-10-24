import assert from 'node:assert/strict'
import fs from 'node:fs'
import { expect } from '@playwright/test'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'

const browser = await launchCoreBrowser(), results = []
try {
    const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
    for (const width of [1440, 390]) {
        const page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        await page.goto('https://wyachts-super-app.test/admin/field-performance/date-range?_measure=1')
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        const flight = page.locator('.fff-dt-range-value-flight')
        const material = page.locator('.fff-dt-range-material')
        const panel = page.locator('[data-fff-calendar-panel]:visible')
        const field = id => page.locator(`[data-benchmark-field="${id}"]`)
        const open = async id => {
            const trigger = field(id).locator('[data-fff-open-trigger]')
            await trigger.scrollIntoViewIfNeeded()
            const rect = await trigger.boundingBox()
            await trigger.click({ position: { x: 12, y: rect.height / 2 } })
        }
        const samplePhase = async (returning, singleLabel = false) => {
            await expect(flight).toHaveCount(1)
            const samples = await flight.evaluate((flight, returning) => {
                const source = flight.querySelector('[data-range-flight-source]'), summary = flight.querySelector('[data-range-flight-summary]')
                if (!source || !summary) throw Error('Missing continuous text labels')
                const effects = [...document.getAnimations()].filter(effect => {
                    const target = effect.effect.target
                    return target?.closest?.('.fff-dt-range-material,.fff-dt-range-value-flight,.fff-dt-range-glyph-flight')
                })
                const duration = Number(flight.getAnimations()[0].effect.getTiming().duration)
                const geometryKeyframes = flight.getAnimations()[0].effect.getKeyframes()
                if (geometryKeyframes.some(frame => Object.hasOwn(frame, 'width') || Object.hasOwn(frame, 'height'))) throw Error('Text spring animates layout dimensions')
                effects.forEach(effect => effect.pause())
                window.__rangeMotionEffects = effects
                return [0, .1, .25, .4, .5, .65, .75, .9, .95, .99].map(progress => {
                    for (const effect of effects) effect.currentTime = duration * progress
                    const sourceStyle = getComputedStyle(source), summaryStyle = getComputedStyle(summary), style = getComputedStyle(flight)
                    return { returning, progress, source: Number(sourceStyle.opacity), summary: Number(summaryStyle.opacity),
                        sourceText: source.textContent, summaryText: summary.textContent, visibility: style.visibility,
                        sourceWidth: source.getBoundingClientRect().width, sourceHeight: source.getBoundingClientRect().height,
                        sourceTruncated: source.scrollWidth > source.clientWidth,
                        summaryWidth: summary.getBoundingClientRect().width, summaryHeight: summary.getBoundingClientRect().height,
                        width: flight.getBoundingClientRect().width, height: flight.getBoundingClientRect().height }
                })
            }, returning)
            for (const sample of samples) {
                assert.ok(Math.abs(sample.sourceWidth - samples[0].sourceWidth) < .1, 'Source text width changed during a spring')
                assert.ok(Math.abs(sample.summaryWidth - samples[0].summaryWidth) < .1, 'Footer text width changed during a spring')
                assert.ok(Math.abs(sample.sourceHeight - samples[0].sourceHeight) < .1, 'Source text height changed during a spring')
                assert.ok(Math.abs(sample.summaryHeight - samples[0].summaryHeight) < .1, 'Footer text rewrapped during a spring')
                assert.equal(sample.sourceTruncated, samples[0].sourceTruncated, 'Spring introduced transient ellipsis')
                assert.ok(Math.abs(sample.source + sample.summary - 1) < .01, 'Text paint disappeared during a spring')
                assert.ok(sample.sourceText && sample.summaryText)
                assert.equal(sample.visibility, 'visible')
                assert.ok(sample.width > 0 && sample.height > 0)
                if (singleLabel) {
                    assert.equal(sample.sourceText, sample.summaryText)
                    assert.ok(sample.source > .99 || sample.summary > .99, 'Identical text was crossfaded over itself')
                }
            }
            await page.evaluate(() => { for (const effect of window.__rangeMotionEffects) effect.finish(); window.__rangeMotionEffects = [] })
            return samples
        }
        for (const id of ['empty-1', 'empty-2', 'empty-3', 'filled-1', 'filled-2', 'filled-3', 'partial-1', 'timed-1', 'timed-2', 'timed-3']) {
            await open(id)
            const enter = await samplePhase(false, ['empty-1', 'filled-1'].includes(id))
            await expect(flight).toHaveCount(0)
            await expect(panel.locator('[data-range-summary]')).toBeVisible()
            await page.keyboard.press('Escape')
            const exit = await samplePhase(true, ['empty-1', 'filled-1'].includes(id))
            await expect(material).toHaveCount(0); await expect(flight).toHaveCount(0)
            await expect(field(id).locator('[data-range-surface-active]')).toHaveCount(0)
            results.push({ width, id, enter, exit })
        }
        for (const id of ['empty-3', 'timed-3']) {
            const root = field(id).locator('[data-fff-civil-date]')
            await root.scrollIntoViewIfNeeded()
            const natural = await root.evaluate(async root => {
                await window.Alpine.$data(root).openCalendar()
                return new Promise((resolve, reject) => {
                    const samples = [], start = performance.now()
                    const frame = () => {
                        const flight = document.querySelector('.fff-dt-range-value-flight')
                        if (flight) {
                            const source = flight.querySelector('[data-range-flight-source]'), summary = flight.querySelector('[data-range-flight-summary]')
                            samples.push({ sourceWidth: source.getBoundingClientRect().width, summaryWidth: summary.getBoundingClientRect().width,
                                sourceHeight: source.getBoundingClientRect().height, summaryHeight: summary.getBoundingClientRect().height,
                                sourceTruncated: source.scrollWidth > source.clientWidth })
                        } else if (samples.length) { resolve(samples); return }
                        if (performance.now() - start > 5000) { reject(Error('Natural text handoff did not complete')); return }
                        requestAnimationFrame(frame)
                    }
                    requestAnimationFrame(frame)
                })
            })
            assert.ok(natural.length > 2)
            for (const sample of natural) {
                for (const key of ['sourceWidth', 'summaryWidth', 'sourceHeight', 'summaryHeight']) assert.ok(Math.abs(sample[key] - natural[0][key]) < .1, `Natural text layout changed: ${id}/${key}`)
                assert.equal(sample.sourceTruncated, natural[0].sourceTruncated, 'Natural motion introduced ellipsis')
            }
            results.push({ width, id, natural: true, samples: natural })
            await page.keyboard.press('Escape'); await expect(material).toHaveCount(0)
        }
        // Reverse during flight through the owner's public calendar commands.
        await open('filled-1')
        await expect(flight).toHaveCount(1)
        await field('filled-1').locator('[data-fff-civil-date]').evaluate(root => window.Alpine.$data(root).closeCalendar())
        await expect(field('filled-1').locator('[data-range-surface-returning]')).toHaveCount(1)
        await field('filled-1').locator('[data-fff-civil-date]').evaluate(root => window.Alpine.$data(root).openCalendar())
        await samplePhase(false); await expect(flight).toHaveCount(0)
        await page.keyboard.press('Escape'); await samplePhase(true); await expect(material).toHaveCount(0)
        // A removed owner releases its in-flight material, glyphs and labels.
        await open('filled-1'); await expect(flight).toHaveCount(1)
        await field('filled-1').locator('[data-fff-civil-date]').evaluate(root => root.remove())
        await expect(material).toHaveCount(0); await expect(flight).toHaveCount(0)
        await expect(page.locator('.fff-dt-range-glyph-flight')).toHaveCount(0)
        assert.deepEqual(errors, [])
        console.log(`PASS ${browserEngine}/${width}: stable text layout through ten enter/exit pairs and natural-frame opens; placeholders, sizes, formats, partial/timed values, reversal and disposal.`)
        await page.close()
    }
    const reducedContext = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true, reducedMotion: 'reduce', viewport: { width: 390, height: 1000 } })
    const reducedPage = await reducedContext.newPage(), reducedErrors = []
    reducedPage.on('pageerror', error => reducedErrors.push(error.message))
    await reducedPage.goto('https://wyachts-super-app.test/admin/field-performance/date-range?_measure=1')
    await reducedPage.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
    for (const id of ['empty-1', 'filled-1']) {
        await reducedPage.locator(`[data-benchmark-field="${id}"] [data-fff-open-trigger]`).click({ position: { x: 12, y: 16 } })
        await expect(reducedPage.locator('[data-fff-calendar-panel]:visible')).toHaveCount(1)
        await expect(reducedPage.locator('[data-fff-calendar-panel]:visible [data-range-summary]')).toBeVisible()
        await expect(reducedPage.locator('.fff-dt-range-material,.fff-dt-range-value-flight')).toHaveCount(0)
        await reducedPage.keyboard.press('Escape')
        await expect(reducedPage.locator('[data-fff-calendar-panel]:visible')).toHaveCount(0)
    }
    assert.deepEqual(reducedErrors, [])
    results.push({ width: 390, reducedMotion: true, fields: 300, placeholderAndValue: true, errors: reducedErrors })
    await reducedContext.close()
    console.log(`PASS ${browserEngine}/reduced-motion: real summary and source are restored without decorative flights.`)
    fs.writeFileSync(process.env.FFF_RANGE_MOTION_OUTPUT ?? `/tmp/fff-range-motion-${browserEngine}.json`, JSON.stringify({ browser: browserEngine, results }, null, 2))
} finally { await browser.close() }
