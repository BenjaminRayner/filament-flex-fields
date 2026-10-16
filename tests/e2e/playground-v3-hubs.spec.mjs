import { test, expect } from '@playwright/test'

import { trackConsoleErrors } from './helpers/console-errors.mjs'
import { ensurePlaygroundAuthenticated, playgroundUrl } from './helpers/playground-auth.mjs'

// Smoke coverage of the registered catalog hubs.
const guidePages = [
    'field-intelligence',
    'select-field',
    'phone-field',
    'country-field',
    'date-time-fields',
    'schedule-field',
    'barcode-scanner-field',
    'choice-cards',
    'segment-tabs',
    'admin-columns',
    'hold-confirm',
    'user-column',
]

const hubExpectations = {
    'field-intelligence': '.fff-intelligence-overview',
    'select-field': '.fff-select-field, .fff-headless-select',
    'admin-columns': '.fff-progress-column, .fff-rating-column',
    'hold-confirm': '[data-fff-hold-confirm]',
    'user-column': '.fff-user-column',
}

test.describe('Flex Fields catalog pages', () => {

    for (const slug of guidePages) {
        test(`${slug} loads without JS errors`, async ({ page }) => {
            const { assertClean } = trackConsoleErrors(page)

            await page.goto(playgroundUrl(slug))
            await ensurePlaygroundAuthenticated(page)
            await page.goto(playgroundUrl(slug))

            await expect(page.locator('body')).toBeVisible()
            await expect(page.locator('.fi-main, .fi-body, main').first()).toBeVisible({ timeout: 30_000 })

            const selector = hubExpectations[slug]

            if (selector) {
                await expect(page.locator(selector).first()).toBeVisible({ timeout: 15_000 })
            }

            assertClean()
        })
    }
})
