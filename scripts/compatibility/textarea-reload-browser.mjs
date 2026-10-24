import assert from 'node:assert/strict'
import fs from 'node:fs'
import { chromium, webkit } from '@playwright/test'

const engine = process.env.FFF_TEXTAREA_BROWSER ?? 'chromium'
assert.ok(['chromium', 'webkit'].includes(engine))
const per = Number(process.env.FFF_TEXTAREA_RELOAD_PER ?? 1)
assert.ok(Number.isInteger(per) && per >= 1 && per <= 50)
const expected = per * 6
const browser = await ({ chromium, webkit })[engine].launch()
const context = await browser.newContext({ storageState: '.auth/playground.json', ignoreHTTPSErrors: true })
await context.addInitScript(expected => {
    window.__textareaHeightFrames = []
    const sample = () => {
        const fields = [...document.querySelectorAll('[data-benchmark-field]')]
        const heights = fields.flatMap(field => {
            const root = field.querySelector('.fff-flex-textarea'), input = root?.querySelector('textarea:not([aria-hidden])')
            if (!input || !getComputedStyle(root).getPropertyValue('--fff-flex-textarea-text-size').trim()) return []
            return [{ key: field.dataset.benchmarkField, height: input.getBoundingClientRect().height, length: (input.value || input.placeholder).length }]
        })
        if (heights.length === expected && window.__textareaHeightFrames.length < 600) window.__textareaHeightFrames.push(heights)
        if (!window.__stopTextareaHeightFrames) requestAnimationFrame(sample)
    }
    requestAnimationFrame(sample)
}, expected)
const results = [], errors = []
try {
    for (const content of ['long', 'placeholder']) for (const width of [1440, 390]) {
        const page = await context.newPage()
        page.on('pageerror', error => errors.push(error.message))
        await page.setViewportSize({ width, height: 1000 })
        let release, requested
        const held = new Promise(resolve => { release = resolve }), request = new Promise(resolve => { requested = resolve })
        await page.route('**/components/flex-textarea.js', async route => { requested(); await held; await route.continue() })
        await page.goto(`https://wyachts-super-app.test/admin/field-performance/textarea?per=${per}&content=${content}&_measure=1`, { waitUntil: 'domcontentloaded' })
        await request
        const before = await page.evaluate(async () => {
            await document.fonts.ready
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))
            return [...document.querySelectorAll('[data-benchmark-field] textarea:not([aria-hidden])')].map(element => ({ height: element.clientHeight, length: (element.value || element.placeholder).length }))
        })
        assert.equal(before.length, expected)
        assert.ok(before.filter(row => row.length > 5000).length >= per * 4)
        release()
        await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt, null, { timeout: 30000 })
        for (const mode of ['held-first-init', 'ordinary-reload']) {
            if (mode === 'ordinary-reload') {
                await page.unroute('**/components/flex-textarea.js')
                await page.reload({ waitUntil: 'domcontentloaded' })
                await page.waitForFunction(() => window.__fffLocalBenchmarkReceipt, null, { timeout: 30000 })
            }
            await page.evaluate(async () => { for (let i = 0; i < 20; i++) await new Promise(requestAnimationFrame); window.__stopTextareaHeightFrames = true })
            const result = await page.evaluate(() => ({ frames: window.__textareaHeightFrames, ready: document.querySelectorAll('.fff-flex-textarea[data-fff-alpine-ready]').length,
                mirrors: document.querySelectorAll('.fff-flex-textarea__measure').length }))
            assert.equal(result.ready, expected)
            assert.ok(result.frames.length > 2)
            const ranges = result.frames[0].map((row, index) => {
                const heights = result.frames.map(frame => frame[index].height)
                return { key: row.key, min: Math.min(...heights), max: Math.max(...heights), length: row.length }
            })
            for (const range of ranges) assert.ok(range.max - range.min < .1, `${engine}/${width}/${mode}: height jump in ${range.key}: ${range.min} → ${range.max}`)
            results.push({ engine, fields: expected, content, width, mode, ready: result.ready, mirrors: result.mirrors, sampledFrames: result.frames.length, ranges })
        }
        await page.close()
    }
    assert.deepEqual(errors, [])
    fs.writeFileSync(process.env.FFF_TEXTAREA_RELOAD_OUTPUT ?? '/tmp/fff-textarea-reload.json', JSON.stringify({ results, errors }, null, 2))
    console.log(`PASS: ${engine}; ${expected} fields; long values/placeholders: paragraphs, 200 lines, unbroken text, fixed rows, moderate text and Unicode; held first init and ordinary reload at 1440/390px, no sampled height jumps.`)
} finally { await browser.close() }
