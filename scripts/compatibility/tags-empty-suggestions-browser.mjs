import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, webkit, expect } from '@playwright/test'

const engine = process.env.FFF_TAGS_BROWSER ?? 'chromium'
const browser = await ({ chromium, webkit }[engine]).launch()
const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
const results = []
try {
    for (const width of [1440, 390]) {
        const page = await context.newPage(), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        await page.goto('https://wyachts-super-app.test/admin/field-performance/tags?_measure=1', { waitUntil: 'domcontentloaded' })
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt, null, { timeout: 30000 })
        assert.equal(await page.locator('[data-benchmark-field]').count(), 300)
        for (const index of [1, 50]) {
            const field = page.locator(`[data-benchmark-field="suggestions-${index}"]`)
            const input = field.locator('[x-ref="headlessInlineSearchInput"]'), root = field.locator('[x-data]').first()
            const query = `unmatched-draft-${index}`
            const assertClosed = async value => {
                await expect(input).toHaveValue(value)
                await expect(input).toHaveAttribute('aria-expanded', 'false')
                await expect.poll(() => page.locator('.fff-select-headless-menu:visible').count()).toBe(0)
                assert.equal(await root.evaluate(el => Alpine.$data(el).comboboxQuery), value)
            }
            await field.scrollIntoViewIfNeeded()
            await input.fill(query)
            await assertClosed(query)
            await input.press('ArrowDown'); await assertClosed(query)
            await input.press('Enter')
            await expect(field.locator('.fff-tags-field__tags-live')).toContainText(query)
            await input.fill('live')
            await expect(page.getByRole('option', { name: 'livewire', exact: true })).toBeVisible()
            const menu = page.locator('.fff-select-headless-menu:visible').first()
            // Exercise the real mobile sheet search, not a hidden trigger input.
            const search = width < 768 ? menu.locator('[x-ref="headlessSearchInput"]') : input
            await search.fill(query + '-again')
            await assertClosed(query + '-again')
            await expect(input).toBeFocused()
            await input.fill('live')
            await page.getByRole('option', { name: 'livewire', exact: true }).click()
            await expect(field.locator('.fff-tags-field__tags-live')).toContainText('livewire')
            await assertClosed('')
            // Already selected suggestions also count as zero visible choices.
            await input.fill('laravel'); await assertClosed('laravel')
            await input.fill('alpine')
            await expect(page.getByRole('option', { name: 'alpinejs', exact: true })).toBeVisible()
            await page.keyboard.press('Escape'); await assertClosed('')
            for (const tag of ['alpinejs', 'tailwindcss', 'pest']) {
                await input.fill(tag); await page.getByRole('option', { name: tag, exact: true }).click()
                await expect(field.locator('.fff-tags-field__tags-live')).toContainText(tag)
            }
            await input.focus(); await assertClosed('')
            results.push({ engine, width, index, pass: true })
        }
        const sibling = page.locator('[data-benchmark-field="suggestions-2"] .fff-tags-field__tags-live .fff-tags-field__tag-label')
        assert.deepEqual(await sibling.allTextContents(), ['laravel', 'filament'])
        const id = await page.locator('[data-benchmark-form]').evaluate(el => el.closest('[' + CSS.escape('wire:id') + ']').getAttribute('wire:id'))
        await page.evaluate(id => window.Livewire.find(id).$commit(), id)
        assert.equal(await page.locator('[data-benchmark-field]').count(), 300)
        await expect(page.locator('[data-benchmark-field="suggestions-1"] .fff-tags-field__tags-live')).toContainText('unmatched-draft-1')
        assert.deepEqual(errors, [])
        await page.close()
    }
    fs.writeFileSync(process.env.FFF_TAGS_OUTPUT ?? '/tmp/fff-tags-empty-browser.json', JSON.stringify(results, null, 2))
    console.log(`PASS: ${engine}, ${results.length} first/last cases, 300 fields at 1440/390px; zero results, draft retention, keyboard, create/reopen/select, mobile sheet focus, exhausted choices, independent owners and Livewire.`)
} finally { await browser.close() }
