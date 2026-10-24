import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, expect } from '@playwright/test'

const inventory = {
    'number-stepper': ['basic', 'decimal', 'bounded', 'nullable', 'localized', 'static'],
    slider: ['basic', 'range', 'decimal', 'elastic', 'marks', 'disabled'],
    rating: ['basic', 'half', 'ten', 'hidden-value', 'readonly', 'disabled'],
    verification: ['basic', 'four', 'grouped', 'alphanumeric', 'prefilled', 'disabled'],
    calculator: ['basic', 'integer', 'decimal', 'bounded', 'affixes', 'readonly'],
    duration: ['basic', 'seconds', 'bounded', 'step', 'empty', 'disabled'],
}
const browser = await chromium.launch()
const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
await context.addInitScript(() => {
    window.__numericEffectErrors = []
    for (const event of ['fff-number-stepper-error', 'fff-slider-error', 'fff-rating-error']) {
        document.addEventListener(event, () => window.__numericEffectErrors.push(event))
    }
})
const results = [], failures = []
const output = process.env.FFF_NUMERIC_OUTPUT ?? '/tmp/fff-numeric-interactions.json'
try {
    for (const width of [1440, 390]) for (const [slug, variants] of Object.entries(inventory)) {
        if (process.env.FFF_NUMERIC_PAGES && !process.env.FFF_NUMERIC_PAGES.split(',').includes(slug)) continue
        const page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        await page.goto(`https://wyachts-super-app.test/admin/field-performance/${slug}?_measure=1`, { waitUntil: 'domcontentloaded' })
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt, null, { timeout: 30000 })
        assert.equal(await page.locator('[data-benchmark-field]').count(), 300)
        for (const variant of variants) for (const index of [1, 50]) {
            const field = page.locator(`[data-benchmark-field="${variant}-${index}"]`)
            const row = { slug, width, variant, index }
            try {
                await field.scrollIntoViewIfNeeded()
                if (slug === 'number-stepper') {
                    const spin = field.getByRole('spinbutton')
                    const before = await spin.getAttribute('aria-valuenow')
                    const start = performance.now()
                    if (variant === 'bounded') {
                        await expect(field.locator('button').last()).toBeDisabled()
                        await spin.press('ArrowDown')
                        await expect(spin).toHaveAttribute('aria-valuenow', '9')
                        await spin.press('End'); await expect(spin).toHaveAttribute('aria-valuenow', '10')
                    } else {
                        await spin.press('ArrowUp')
                        const wanted = variant === 'decimal' ? '2.75' : variant === 'nullable' ? '1' : variant === 'localized' ? '1251' : '11'
                        await expect(spin).toHaveAttribute('aria-valuenow', wanted)
                        await field.locator('button').first().click()
                        if (variant !== 'nullable') await expect(spin).toHaveAttribute('aria-valuenow', before)
                    }
                    row.keyboardAndButtonsMs = performance.now() - start
                } else if (slug === 'slider') {
                    const thumb = field.getByRole('slider').first()
                    if (variant === 'disabled') {
                        const before = await thumb.getAttribute('aria-valuenow')
                        await thumb.dispatchEvent('keydown', { key: 'ArrowRight', bubbles: true })
                        await expect(thumb).toHaveAttribute('aria-valuenow', before)
                    } else {
                        const before = Number(await thumb.getAttribute('aria-valuenow'))
                        await thumb.press('ArrowRight')
                        await expect.poll(async () => Number(await thumb.getAttribute('aria-valuenow'))).toBeGreaterThan(before)
                        await thumb.press('Home'); await expect(thumb).toHaveAttribute('aria-valuenow', '0')
                        if (variant !== 'range') { await thumb.press('End'); await expect(thumb).toHaveAttribute('aria-valuenow', '100') }
                    }
                } else if (slug === 'rating') {
                    if (['half', 'readonly'].includes(variant)) {
                        assert.equal(await field.getByRole('radio').count(), 0)
                        await expect(field.getByRole('img')).toBeVisible()
                    } else if (variant === 'disabled') await expect(field.getByRole('radio').first()).toBeDisabled()
                    else {
                        const radios = field.getByRole('radio')
                        await radios.nth(3).click(); await expect(radios.nth(3)).toHaveAttribute('aria-checked', 'true')
                        await radios.nth(3).press('Home'); await expect(radios.first()).toHaveAttribute('aria-checked', 'true')
                        await radios.first().press('End'); await expect(radios.last()).toHaveAttribute('aria-checked', 'true')
                    }
                } else if (slug === 'verification') {
                    const inputs = field.locator('[data-verification-index]')
                    if (variant === 'disabled') await expect(inputs.first()).toBeDisabled()
                    else {
                        const code = variant === 'four' ? '2468' : variant === 'alphanumeric' ? 'A1B2C3' : '246810'
                        await inputs.first().evaluate((element, code) => {
                            const data = new DataTransfer(); data.setData('text/plain', code)
                            element.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, clipboardData: data }))
                        }, code)
                        await expect.poll(async () => (await inputs.evaluateAll(elements => elements.map(el => el.value))).join('')).toBe(code)
                        await inputs.last().press('Backspace')
                        await expect(inputs.last()).toHaveValue('')
                        await inputs.last().fill(code.at(-1)); await expect(inputs.last()).toHaveValue(code.at(-1))
                    }
                } else if (slug === 'calculator') {
                    const input = field.locator('[data-fff-text-input] input')
                    const trigger = field.locator('[data-fff-calculator-trigger]')
                    if (variant === 'readonly') { await expect(input).toHaveAttribute('readonly'); await expect(trigger).toBeDisabled() }
                    else {
                        await input.fill('7'); await input.blur()
                        await trigger.click()
                        const panel = page.locator('.fff-calculator-surface').filter({ visible: true })
                        await expect(panel).toBeVisible()
                        for (const key of ['AC', '1', '+', '2', '=']) await panel.locator(`[data-calculator-key="${key}"]`).click()
                        await panel.locator('[data-calculator-key="apply"]').click()
                        await expect(input).toHaveValue('3')
                        await expect(panel).toBeHidden()
                    }
                } else if (slug === 'duration') {
                    const input = field.locator('[data-duration-input]')
                    if (variant === 'disabled') await expect(input).toBeDisabled()
                    else {
                        await input.fill(variant === 'seconds' ? '03:00:30' : '03:00'); await input.blur()
                        await input.press('ArrowUp')
                        await expect(input).toHaveValue(variant === 'seconds' ? '03:00:45' : variant === 'step' ? '03:15' : '03:01')
                        await field.locator('button').first().click()
                        await expect(input).toHaveValue(variant === 'seconds' ? '03:00:30' : '03:00')
                    }
                }
                row.pass = true
            } catch (error) { row.failure = error.message; failures.push(row); console.error(JSON.stringify(row)) }
            results.push(row)
            fs.writeFileSync(output, JSON.stringify({ results, failures }, null, 2))
        }
        const selectionSnapshot = () => page.locator('[data-benchmark-field]').evaluateAll(fields => fields.map(field => ({
            key: field.dataset.benchmarkField,
            values: [...field.querySelectorAll('input')].map(input => input.value),
            numeric: [...field.querySelectorAll('[role=spinbutton], [role=slider]')].map(input => input.getAttribute('aria-valuenow')),
            rating: [...field.querySelectorAll('[role=radio]')].map(input => input.getAttribute('aria-checked')),
        })))
        const beforeCommit = await selectionSnapshot()
        // Flush actual native Livewire transport without persisting synthetic records.
        const componentId = await page.locator('[data-benchmark-form]').evaluate(el => el.closest('[' + CSS.escape('wire:id') + ']').getAttribute('wire:id'))
        await page.evaluate(id => window.Livewire.find(id).$commit(), componentId)
        await expect.poll(() => page.locator('[data-benchmark-field]').count()).toBe(300)
        await expect.poll(selectionSnapshot).toEqual(beforeCommit)
        assert.deepEqual(await page.evaluate(() => window.__numericEffectErrors), [])
        assert.deepEqual(errors, [])
        console.log(`Checked ${slug} at ${width}px, 300 fields; ${results.filter(r => r.slug === slug && r.width === width && r.pass).length}/12 cases passed`)
        await page.close()
    }
    assert.deepEqual(failures, [])
    console.log(`PASS: ${results.length} cases, first/last owner in each variant, desktop/mobile, native Livewire commit.`)
} finally { await browser.close() }
