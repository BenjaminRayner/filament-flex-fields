import { test, expect } from '@playwright/test'
import zxing from '@zxing/library'
import { trackConsoleErrors } from './helpers/console-errors.mjs'

const encoded = new zxing.QRCodeWriter().encode('FLEX-QR-TEST-2026', zxing.BarcodeFormat.QR_CODE, 33, 33, new Map())
const matrix = Array.from({ length: encoded.getHeight() }, (_, y) => Array.from({ length: encoded.getWidth() }, (_, x) => encoded.get(x, y) ? 1 : 0))

async function syntheticCamera(page) {
    await page.addInitScript(rows => {
        const canvas = document.createElement('canvas')
        canvas.width = 1280; canvas.height = 720
        const context = canvas.getContext('2d')
        context.fillStyle = '#343438'; context.fillRect(0, 0, 1280, 720)
        context.fillStyle = '#fff'; context.fillRect(418, 138, 444, 444)
        rows.forEach((row, y) => row.forEach((dark, x) => {
            context.fillStyle = dark ? '#000' : '#fff'
            context.fillRect(442 + x * 12, 162 + y * 12, 12, 12)
        }))
        window.__barcodeCamera = { streams: [], scans: [] }
        document.addEventListener('barcode-scanned', event => window.__barcodeCamera.scans.push(event.detail))
        Object.defineProperty(navigator.mediaDevices, 'getUserMedia', { configurable: true, value: async () => {
            const stream = canvas.captureStream(15)
            window.__barcodeCamera.streams.push(stream)
            return stream
        } })
        Object.defineProperty(navigator.mediaDevices, 'enumerateDevices', { configurable: true, value: async () => [] })
        const ticker = setInterval(() => { context.fillStyle = '#343438'; context.fillRect(0, 0, 10, 10) }, 60)
        addEventListener('pagehide', () => clearInterval(ticker), { once: true })
    }, matrix)
}

test.describe('Flex Fields playground barcode scanner', () => {
    test('renders one shared input per field and no eager ZXing decoder', async ({ page }) => {
        const { assertClean } = trackConsoleErrors(page)
        await page.goto('/barcode-scanner-field')
        const field = page.locator('.fff-barcode-scanner').first()
        await expect(field).toHaveAttribute('data-barcode-ready', '')
        await expect(field.locator('input[data-barcode-input]')).toHaveCount(1)
        await expect(field.locator('.fff-flex-text-input__shell')).toBeVisible()
        await expect(field.locator('[data-barcode-trigger]')).toBeVisible()
        expect(await page.evaluate(() => performance.getEntriesByType('resource').some(entry => /barcode-decoder-zxing/.test(entry.name)))).toBe(false)
        assertClean()
    })
    test('decodes a real QR image, commits once and stops the stream', async ({ page }) => {
        const { assertClean } = trackConsoleErrors(page)
        await syntheticCamera(page)
        await page.goto('/barcode-scanner-field')
        await expect(page.locator('.fff-barcode-scanner').first()).toHaveAttribute('data-barcode-ready', '')
        await page.locator('[data-barcode-trigger]').first().click()
        await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('FLEX-QR-TEST-2026')
        await expect(page.locator('.fff-barcode-camera[open]')).toHaveCount(0)
        expect(await page.evaluate(() => window.__barcodeCamera.scans.length)).toBe(1)
        expect(await page.evaluate(() => window.__barcodeCamera.streams.every(stream => stream.getTracks().every(track => track.readyState === 'ended')))).toBe(true)
        assertClean()
    })
    test('reviews a frozen frame without committing until Save', async ({ page }) => {
        const { assertClean } = trackConsoleErrors(page)
        await syntheticCamera(page)
        await page.goto('/barcode-scanner-field')
        const input = page.getByLabel('Preview and confirm the scan', { exact: true })
        const field = page.locator('.fff-barcode-scanner').filter({ has: input })
        await expect(field).toHaveAttribute('data-barcode-ready', '')
        await field.locator('[data-barcode-trigger]').click()
        const dialog = page.locator('.fff-barcode-camera[open]')
        await expect(dialog.getByRole('button', { name: 'Save code', exact: true })).toBeVisible()
        await expect(dialog.locator('canvas')).toBeVisible()
        await expect(input).toHaveValue('')
        expect(await page.evaluate(() => window.__barcodeCamera.streams.every(stream => stream.getTracks().every(track => track.readyState === 'ended')))).toBe(true)
        await dialog.getByRole('button', { name: 'Scan again', exact: true }).click()
        await expect(dialog.getByRole('button', { name: 'Save code', exact: true })).toBeVisible()
        await expect(input).toHaveValue('')
        await dialog.getByRole('button', { name: 'Save code', exact: true }).click()
        await expect(input).toHaveValue('FLEX-QR-TEST-2026')
        await expect(page.locator('.fff-barcode-camera[open]')).toHaveCount(0)
        expect(await page.evaluate(() => window.__barcodeCamera.scans.length)).toBe(1)
        assertClean()
    })
})
