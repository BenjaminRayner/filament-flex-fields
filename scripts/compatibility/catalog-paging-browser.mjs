import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, expect } from '@playwright/test'

// Local synthetic native-panel fixture only. No customer data or server settings.
const browser = await chromium.launch(), results = []
try {
    const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
    for (const width of [1440, 390]) {
        const page = await context.newPage(), errors = [], calls = []
        page.on('pageerror', error => errors.push(error.message))
        page.on('request', request => {
            if (request.method() !== 'POST' || !request.url().includes('livewire')) return
            for (const component of request.postDataJSON()?.components ?? []) {
                for (const call of component.calls ?? []) if (call.method === 'callSchemaComponentMethod') calls.push(call.params?.[1])
            }
        })
        await page.setViewportSize({ width, height: 1000 })
        await page.goto('https://wyachts-super-app.test/admin/field-performance/catalogs?_measure=1')
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt)
        for (const variant of ['country', 'icon', 'font']) {
            const field = page.locator(`[data-benchmark-field="${variant}-1"]`)
            const state = () => field.evaluate(field => {
                const root = [...field.querySelectorAll('[x-data]')].find(el => el._x_dataStack?.[0]?.comboboxOpen)
                const data = window.Alpine.$data(root)
                return { options: data.options.length, rows: data._virtualFlatRows.length }
            })
            await field.locator('[data-fff-open-trigger]').first().click()
            const menu = page.locator('.fff-select-headless-menu:visible')
            await expect(menu).toHaveCount(1)
            await expect.poll(async () => (await state()).options).toBeGreaterThan(20)
            const initial = await state()
            const list = menu.locator('[x-ref="headlessOptionsList"]')
            await expect(list).toHaveAttribute('data-fff-virtualized', 'true')
            const mounted = await menu.getByRole('option').count()
            // A short first grid page can fit entirely within its overscan.
            if (variant !== 'icon') assert.ok(mounted < initial.options, `${variant}: all options mounted instead of a virtual window`)
            const callsBefore = calls.length
            await list.evaluate(list => { list.scrollTop = list.scrollHeight; list.dispatchEvent(new Event('scroll')) })
            if (variant !== 'country') {
                await expect.poll(async () => (await state()).options, { timeout: 15000 }).toBeGreaterThan(initial.options)
                assert.ok(calls.slice(callsBefore).includes(variant === 'font' ? 'getFontSelectPage' : 'getIconPickerSelectPage'))
            } else {
                await expect.poll(() => list.evaluate(list => list.scrollTop)).toBeGreaterThan(100)
                await expect(menu.getByRole('option').last()).toBeVisible()
            }
            const afterScroll = await state()
            await expect.poll(() => menu.getByRole('option').count()).toBeLessThan(afterScroll.options)
            await menu.locator('input[type="search"]').fill(variant === 'country' ? 'Germany' : variant === 'icon' ? 'star' : 'Roboto')
            const option = menu.getByRole('option').filter({ hasText: variant === 'country' ? 'Germany' : variant === 'icon' ? /star/i : 'Roboto' }).first()
            await expect(option).toBeVisible({ timeout: 15000 })
            await option.click()
            await expect(field.locator('[data-fff-open-trigger]').first()).toContainText(variant === 'country' ? 'Germany' : variant === 'icon' ? /star/i : 'Roboto')
            await page.keyboard.press('Escape'); await page.locator('h1').first().click()
            results.push({ width, variant, initial, initiallyMountedOptions: mounted, afterScroll, searchedAndSelected: true, nativePageRequest: variant !== 'country' })
        }
        assert.deepEqual(errors, [])
        await page.close()
    }
    fs.writeFileSync(process.env.FFF_CATALOG_PAGING_OUTPUT ?? '/tmp/fff-catalog-paging.json', JSON.stringify({ browser: 'Chromium', results }, null, 2))
    console.log('PASS: six desktop/mobile virtualization, real infinite pagination, search and selection cases on the300-field catalog page.')
} finally { await browser.close() }
