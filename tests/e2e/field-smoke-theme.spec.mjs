import { test, expect } from '@playwright/test'
import { readFileSync } from 'node:fs'

const themeView = readFileSync(new URL('../../resources/views/partials/flex-fields-theme.blade.php', import.meta.url), 'utf8')
const baseCss = readFileSync(new URL('../../resources/css/base.css', import.meta.url), 'utf8')
// Use the template's actual selector and the package defaults to exercise the cascade.
const themeCss = themeView.match(/\s+([^\n]+) \{\s*@foreach[\s\S]*?@endforeach\s*\}/)[0]
    .replace(/@foreach[\s\S]*?@endforeach/, `
        --fff-field-bg: rgb(240 241 242);
        --fff-field-border-width: 0px;
        --fff-field-shadow: 0 0 0 1px rgb(3 7 18 / 0.1);
    `)

for (const mode of ['light', 'dark']) {
    test(`configured theme tokens survive later-loaded defaults in ${mode} mode`, async ({ page }) => {
        await page.setContent(`
            <html class="${mode === 'dark' ? 'dark' : ''}">
                <head><style>${themeCss}</style></head>
                <body><div id="field" style="--fff-field-bg: rgb(10 20 30)"></div></body>
            </html>
        `)
        await page.addStyleTag({ content: baseCss })

        const tokens = await page.evaluate(() => {
            const root = getComputedStyle(document.documentElement)
            return {
                background: root.getPropertyValue('--fff-field-bg').trim(),
                borderWidth: root.getPropertyValue('--fff-field-border-width').trim(),
                shadow: root.getPropertyValue('--fff-field-shadow').trim(),
                fieldBackground: getComputedStyle(document.querySelector('#field')).getPropertyValue('--fff-field-bg').trim(),
            }
        })

        expect(tokens).toEqual({
            background: 'rgb(240 241 242)',
            borderWidth: '0px',
            shadow: '0 0 0 1px rgb(3 7 18 / 0.1)',
            fieldBackground: 'rgb(10 20 30)',
        })
    })
}
