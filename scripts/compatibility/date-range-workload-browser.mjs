import assert from 'node:assert/strict'
import fs from 'node:fs'
import { expect } from '@playwright/test'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'

// Real local native panel; synthetic form state only, no persistence.
const browser = await launchCoreBrowser(), results = []
try {
    const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
    for (const width of [1440, 390]) {
        const page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        let release, requested
        const held = new Promise(resolve => { release = resolve }), request = new Promise(resolve => { requested = resolve })
        await page.route(/\/components\/flex-date-picker\.js$/, async route => { requested(); await held; await route.continue() })
        await page.goto('https://wyachts-super-app.test/admin/field-performance/date-range?_measure=1', { waitUntil: 'domcontentloaded' })
        await request
        await page.evaluate(() => document.fonts.ready)
        const heights = () => page.locator('[data-benchmark-field] .fff-flex-text-input__shell').evaluateAll(nodes => nodes.map(el => el.getBoundingClientRect().height))
        const beforeInit = await heights()
        release()
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        const receipt = await page.evaluate(() => window.__fffLocalBenchmarkReceipt)
        assert.equal(receipt.fields, 300); assert.equal(receipt.activeFactories, 300)
        assert.deepEqual(await heights(), beforeInit, 'Shell heights changed at factory initialization')
        const variants = ['empty', 'filled', 'partial', 'timed', 'presets', 'states']
        for (const variant of variants) await expect(page.locator(`[data-benchmark-field^="${variant}-"]`)).toHaveCount(50)
        const field = id => page.locator(`[data-benchmark-field="${id}"]`)
        const state = id => field(id).locator('[data-fff-civil-date]').evaluate(root => JSON.parse(JSON.stringify(window.Alpine.$data(root).state)))
        assert.deepEqual(await state('partial-1'), { start: '2026-10-01', end: null })
        assert.deepEqual(await state('partial-2'), { start: null, end: '2026-10-08' })
        const panel = page.locator('[data-fff-calendar-panel]:visible')
        const open = async id => {
            const trigger = field(id).locator('[data-fff-open-trigger]')
            await trigger.scrollIntoViewIfNeeded()
            const box = await trigger.boundingBox()
            await trigger.click({ position: { x: 12, y: box.height / 2 } })
        }
        const close = async () => {
            await page.keyboard.press('Escape')
            await expect(panel).toHaveCount(0)
        }
        const openings = []
        for (const variant of variants) for (const index of [1, 50]) {
            const id = `${variant}-${index}`
            if (id === 'states-1') continue // Disabled is exercised separately.
            await open(id)
            await expect(panel).toHaveCount(1)
            await expect(panel.locator('[role="grid"]')).not.toHaveCount(0)
            await expect(field(id).locator('[role="alert"]')).not.toBeVisible()
            if (variant === 'empty') {
                await expect(page.locator('.fff-dt-range-value-flight')).toHaveCount(0)
                const placeholder = await field(id).locator('input').first().getAttribute('placeholder')
                await expect(panel.locator('[data-range-summary]')).toHaveText(placeholder)
                await expect(panel.locator('[data-range-count]')).not.toBeVisible()
                const centers = await panel.evaluate(panel => {
                    const summary = panel.querySelector('.fff-dt-range__summary').getBoundingClientRect()
                    const actions = panel.querySelector('.fff-dt-range__actions').getBoundingClientRect()
                    return { sameRow: summary.bottom > actions.top && actions.bottom > summary.top,
                        difference: Math.abs(summary.top + summary.height / 2 - actions.top - actions.height / 2) }
                })
                if (centers.sameRow) assert.ok(centers.difference < 1, 'Empty summary is not vertically centered with footer actions')
            }
            await close(); openings.push(id)
        }
        for (const id of ['states-1', 'states-2']) {
            await open(id); await expect(panel).toHaveCount(0)
        }
        for (const id of ['states-3', 'states-4']) {
            await expect(field(id).locator('input').first()).toHaveAttribute('aria-invalid', 'true')
            await open(id); await expect(panel).toHaveCount(1)
            await expect(panel.locator('[data-command="apply"]')).toBeDisabled()
            await close()
        }
        await open('presets-1'); await expect(panel.locator('[data-range-presets] button')).toHaveCount(0); await close()
        await open('presets-2'); await expect(panel.locator('[data-range-presets] button')).toHaveCount(2); await close()
        await open('presets-3'); await expect(panel.locator('[data-range-presets] button')).toHaveCount(8); await close()
        await open('empty-1')
        const days = panel.locator('[data-date]:not(:disabled)')
        const start = await days.nth(8).getAttribute('data-date'), end = await days.nth(12).getAttribute('data-date')
        await days.nth(8).click(); await days.nth(12).click()
        await panel.locator('[data-command="apply"]').click(); await expect(panel).toHaveCount(0)
        assert.deepEqual(await state('empty-1'), { start, end }); assert.equal(await state('empty-2'), null)
        await page.evaluate(async () => {
            window.__rangeOwners = [...document.querySelectorAll('[data-fff-civil-date]')]
            const root = document.querySelector('[data-benchmark-form]').closest('[wire\\:id]')
            await Livewire.find(root.getAttribute('wire:id')).$commit()
        })
        assert.deepEqual(await state('empty-1'), { start, end })
        assert.equal(await page.evaluate(() => window.__rangeOwners.every(el => el.isConnected)), true)
        await page.unroute(/\/components\/flex-date-picker\.js$/)
        await page.reload({ waitUntil: 'domcontentloaded' }); await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        assert.deepEqual(await heights(), beforeInit, 'Reload changed shell heights')
        assert.deepEqual(errors, [])
        results.push({ width, fields: 300, activeFactories: 300, openings, independentSelection: true, partialValuesRetained: true, disabledReadOnlyInvalidStates: true, presets: [0, 2, 8], nativeMorphRetainsOwners: true, stableHeldInitAndReload: true, errors })
        console.log(`PASS ${browserEngine}/${width}:300 Date Range owners, six variants, first/last opens, states, presets, selection, native morph and held-init/reload geometry.`)
        await page.close()
    }
    fs.writeFileSync(process.env.FFF_DATE_RANGE_OUTPUT ?? `/tmp/fff-date-range-workload-${browserEngine}.json`, JSON.stringify({ browser: browserEngine, results }, null, 2))
} finally { await browser.close() }
