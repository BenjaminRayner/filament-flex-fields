import { test, expect } from '@playwright/test'
import { readFileSync } from 'node:fs'

const themeView = readFileSync(new URL('../../resources/views/partials/flex-fields-theme.blade.php', import.meta.url), 'utf8')
const stylesheets = {
    source: readFileSync(new URL('../../resources/css/base.css', import.meta.url), 'utf8'),
    built: readFileSync(new URL('../../resources/dist/css/core.css', import.meta.url), 'utf8'),
}
// PHP tests cover token rendering; retain the real Blade selector for cascade coverage.
const themeCss = themeView.match(/\s+([^\n]+) \{\s*@foreach[\s\S]*?@endforeach\s*\}/)[0]
    .replace(/@foreach[\s\S]*?@endforeach/, `
        --fff-field-bg: rgb(240 241 242);
        --fff-field-border-width: 0px;
        --fff-field-shadow: 0 0 0 1px rgb(3 7 18 / 0.1);
    `)

for (const [stylesheet, baseCss] of Object.entries(stylesheets)) {
    for (const mode of ['light', 'dark']) {
        for (const defaultsFirst of [false, true]) {
            test(`${stylesheet} ${mode} theme preserves configured and field-local tokens with defaults ${defaultsFirst ? 'first' : 'last'}`, async ({ page }) => {
                await page.setContent(`
                    <html class="${mode === 'dark' ? 'dark' : ''}">
                        <head></head>
                        <body>
                            <div id="inherited" style="background:var(--fff-field-bg);border:var(--fff-field-border-width) solid;box-shadow:var(--fff-field-shadow)">Inherited</div>
                            <div id="field" style="--fff-field-bg:rgb(10 20 30);background:var(--fff-field-bg)">Local</div>
                        </body>
                    </html>
                `)
                for (const content of defaultsFirst ? [baseCss, themeCss] : [themeCss, baseCss]) {
                    await page.addStyleTag({ content })
                }

                const tokens = await page.evaluate(() => {
                    const root = getComputedStyle(document.documentElement)
                    return {
                        background: root.getPropertyValue('--fff-field-bg').trim(),
                        borderWidth: root.getPropertyValue('--fff-field-border-width').trim(),
                        shadow: root.getPropertyValue('--fff-field-shadow').trim(),
                    }
                })
                expect(tokens).toEqual({
                    background: 'rgb(240 241 242)',
                    borderWidth: '0px',
                    shadow: '0 0 0 1px rgb(3 7 18 / 0.1)',
                })
                await expect(page.locator('#inherited')).toHaveCSS('background-color', 'rgb(240, 241, 242)')
                await expect(page.locator('#inherited')).toHaveCSS('border-top-width', '0px')
                await expect(page.locator('#field')).toHaveCSS('background-color', 'rgb(10, 20, 30)')

                // Switching themes still changes unconfigured tokens while preserving overrides.
                const borderBefore = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--fff-field-border').trim())
                await page.evaluate(() => document.documentElement.classList.toggle('dark'))
                const borderAfter = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--fff-field-border').trim())
                expect(borderAfter).not.toBe(borderBefore)
                await expect(page.locator('#inherited')).toHaveCSS('background-color', 'rgb(240, 241, 242)')
                await expect(page.locator('#field')).toHaveCSS('background-color', 'rgb(10, 20, 30)')
            })
        }
    }
}
