import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, firefox, webkit, expect } from '@playwright/test'

const engine = process.env.FFF_TEXTAREA_BROWSER ?? 'chromium'
assert.ok(['chromium', 'firefox', 'webkit'].includes(engine))
const browser = await ({ chromium, firefox, webkit })[engine].launch()
const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
const results = [], errors = []
try {
    const page = await context.newPage()
    page.on('pageerror', error => errors.push(error.message))
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.goto('https://wyachts-super-app.test/admin/field-performance/textarea?_measure=1', { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt, null, { timeout: 30000 })
    assert.equal(await page.locator('.fff-flex-textarea[data-fff-alpine-ready]').count(), 300)
    const input = page.locator('[data-benchmark-field="basic-50"] textarea:not([aria-hidden])')
    const neighbor = page.locator('[data-benchmark-field="basic-49"] textarea:not([aria-hidden])')
    const neighborValue = await neighbor.inputValue()
    const text = 'An independent textarea keeps its complete contents while its available width and font change. '.repeat(5)
    await input.fill(text)
    for (const font of ['Arial', 'Georgia', 'monospace']) for (const dir of ['ltr', 'rtl']) for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 })
        await input.evaluate((element, { font, dir }) => {
            document.documentElement.dir = dir
            element.style.fontFamily = font
            element.style.fontSize = '20px'
            element.style.lineHeight = '1.6'
            // Font notifications are the public platform signal used by owners.
            document.fonts.dispatchEvent(new Event('loadingdone'))
        }, { font, dir })
        await expect.poll(() => input.evaluate(element => {
            const css = getComputedStyle(element), mirror = element.closest('.fff-flex-textarea').querySelector('.fff-flex-textarea__measure')
            if (Math.abs(parseFloat(mirror.style.width) - element.clientWidth) > .1 || mirror.style.fontFamily !== css.fontFamily) return Infinity
            const expected = Math.max(parseFloat(css.minHeight), Math.min(mirror.scrollHeight, parseFloat(css.maxHeight)))
            return Math.abs(element.getBoundingClientRect().height - expected)
        })).toBeLessThan(.1)
        await expect(input).toHaveValue(text)
        await expect(neighbor).toHaveValue(neighborValue)
        const row = await input.evaluate(element => {
            const css = getComputedStyle(element)
            return { height: element.clientHeight, scrollHeight: element.scrollHeight, maximum: parseFloat(css.maxHeight), transition: css.transitionDuration, font: css.fontFamily }
        })
        assert.equal(row.transition, '0.28s')
        assert.ok(row.height >= row.maximum || row.scrollHeight <= row.height + 1)
        results.push({ width, dir, ...row })
    }
    await input.evaluate(async element => {
        const owner = element.closest('.fff-flex-textarea')
        window.__textareaOwnerBeforeMorph = owner
        const id = owner.closest('[wire\\:id]').getAttribute('wire:id')
        await window.Livewire.find(id).$set(owner._x_dataStack[0].options.statePath, 'Reset from Livewire')
    })
    await expect(input).toHaveValue('Reset from Livewire')
    assert.equal(await input.evaluate(element => element.closest('.fff-flex-textarea') === window.__textareaOwnerBeforeMorph), true)
    await expect(neighbor).toHaveValue(neighborValue)
    await expect.poll(() => input.evaluate(element => Math.abs(element.getBoundingClientRect().height - parseFloat(element.style.height)))).toBeLessThan(.1)
    // Disposal during a queued measurement cannot resurrect this owner or stop
    // the neighboring field's subsequent edit/autosize.
    await input.evaluate(element => {
        const root = element.closest('.fff-flex-textarea')
        element.value = 'Pending edit'; element.dispatchEvent(new Event('input', { bubbles: true }))
        window.Alpine.destroyTree(root)
        root.remove()
    })
    await neighbor.fill('Surviving owner\n'.repeat(30))
    await expect.poll(() => neighbor.evaluate(element => element.clientHeight)).toBe(384)
    assert.equal(await page.locator('[data-textarea-measure-host]').count(), 249)
    assert.equal(await neighbor.evaluate(element => element.closest('.fff-flex-textarea').querySelectorAll('.fff-flex-textarea__measure').length), 1)
    assert.deepEqual(errors, [])
    const output = process.env.FFF_TEXTAREA_LAYOUT_OUTPUT ?? '/tmp/fff-textarea-layout.json'
    fs.writeFileSync(output, JSON.stringify({ engine, fieldsBeforeDisposal: 300, results, sameOwnerAfterLivewireReset: true, disposal: 'queued owner removed; neighbor remains editable and autosizes', errors }, null, 2))
    console.log(`PASS: ${engine}; ${results.length} font/width/direction combinations; animation, clipping bounds, independent values, Livewire reset/morph and disposal.`)
} finally { await browser.close() }
