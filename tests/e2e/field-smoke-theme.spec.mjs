import { test, expect } from '@playwright/test'
import { readFileSync } from 'node:fs'

const themeView = readFileSync(new URL('../../resources/views/partials/flex-fields-theme.blade.php', import.meta.url), 'utf8')
const stylesheets = {
    source: readFileSync(new URL('../../resources/css/base.css', import.meta.url), 'utf8'),
    built: readFileSync(new URL('../../resources/dist/css/core.css', import.meta.url), 'utf8'),
}

const selectCss = readFileSync(new URL('../../resources/dist/css/select-field.css', import.meta.url), 'utf8')

for (const wrapper of ['fff-select-field', 'fff-select-trigger-ssr']) {
    for (const mode of ['light', 'dark']) {
        for (const direction of ['ltr', 'rtl']) {
            test(`trailing selected description is styled in ${wrapper} ${mode} ${direction}`, async ({ page }) => {
                await page.setContent(`
                    <html class="${mode === 'dark' ? 'dark' : ''}" dir="${direction}">
                        <body>
                            <div class="${wrapper}" style="--fff-select-rich-desc-color:rgb(90 100 110);--fff-select-rich-desc-size:13px">
                                <div class="fi-select-input-value-label" style="width:300px">
                                    <span class="fff-select-option fff-select-option--list fff-select-option--description-trailing">
                                        <span class="fff-select-option__content"><span class="fff-select-option__label">Pro</span></span>
                                        <span class="fff-select-option__trailing-description" dir="auto">Advanced plan</span>
                                    </span>
                                </div>
                            </div>
                        </body>
                    </html>
                `)
                await page.addStyleTag({ content: selectCss })

                const description = page.locator('.fff-select-option__trailing-description')
                await expect(description).toHaveCSS('color', 'rgb(90, 100, 110)')
                await expect(description).toHaveCSS('font-size', '13px')
                await expect(description).toHaveCSS('white-space', 'nowrap')
                const labelBox = await page.locator('.fff-select-option__label').boundingBox()
                const descriptionBox = await description.boundingBox()
                expect(Math.abs(labelBox.y - descriptionBox.y)).toBeLessThan(8)
                if (direction === 'ltr') {
                    expect(descriptionBox.x).toBeGreaterThan(labelBox.x + labelBox.width)
                } else {
                    expect(descriptionBox.x + descriptionBox.width).toBeLessThan(labelBox.x)
                }
            })
        }
    }
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
