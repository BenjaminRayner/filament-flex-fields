import { test, expect } from '@playwright/test'
import { build } from 'esbuild'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('../..', import.meta.url))
const bundle = await build({
    stdin: { resolveDir: root, contents: `
        import { createInputSuggestions } from './resources/js/components/text-input/suggestions.js';
        window.mountSuggestions = () => {
            const input = document.querySelector('input');
            window.owner = { $refs: { input }, $el: input.parentElement,
                options: { editable: true, suggestionsEnabled: true, suggestionMinLength: 0,
                    suggestionDebounce: 0, suggestionLimit: 20, suggestionPlacement: 'auto',
                    messages: { suggestions_empty: 'No suggestions', suggestions_empty_hint: 'Enter a value',
                        suggestions_error: 'Error', suggestions_loading: 'Loading', retry: 'Retry' },
                    suggestions: Array.from({ length: 12 }, (_, index) => ({ value: 'value-' + index, label: 'Option ' + index })),
                }, commitToolValue(value) { input.value = value; },
            };
            window.suggestions = createInputSuggestions(owner);
            input.addEventListener('input', () => suggestions.schedule());
            input.focus(); suggestions.schedule();
        };
    ` },
    bundle: true, write: false, format: 'iife', logLevel: 'silent',
})
const css = ['core.css', 'overlay-runtime.css', 'select-field.css', 'flex-text-input.css']
    .map(name => readFileSync(new URL(`../../resources/dist/css/${name}`, import.meta.url), 'utf8')).join('\n')

test('native mobile handle drag preserves input focus, then option selection and blur still close suggestions', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.setContent('<div class="fff-flex-text-input__shell" style="margin:80px 24px"><input aria-label="Subject" id="subject" style="height:40px;width:100%"></div><button id="outside" style="position:fixed;top:5px">Outside</button>')
    await page.addStyleTag({ content: css })
    await page.addScriptTag({ content: bundle.outputFiles[0].text })
    await page.evaluate(() => window.mountSuggestions())
    const panel = page.locator('.fff-input-suggestions')
    const handle = panel.locator('[data-fff-overlay-handle]')
    await expect(handle).toBeVisible()
    await expect(panel).toHaveClass(/is-positioned/)
    await expect(panel).not.toHaveAttribute('data-fff-sheet-entering', 'true')
    const bounds = await handle.boundingBox()
    const x = bounds.x + bounds.width / 2
    const y = bounds.y + bounds.height / 2
    await page.mouse.move(x, y)
    await page.mouse.down()
    await page.mouse.move(x, y + 35, { steps: 8 })
    await expect(page.getByLabel('Subject')).toBeFocused()
    await expect(panel).toHaveAttribute('data-fff-sheet-dragging', 'true')
    expect(await page.evaluate(() => owner.suggestionsOpen)).toBe(true)
    await page.mouse.move(x, y, { steps: 8 })
    await page.mouse.up()
    await expect(page.getByLabel('Subject')).toBeFocused()
    await panel.getByRole('option', { name: 'Option 0', exact: true }).click()
    await expect(page.getByLabel('Subject')).toHaveValue('value-0')
    expect(await page.evaluate(() => owner.suggestionsOpen)).toBe(false)

    await page.getByLabel('Subject').fill('Option')
    await expect(panel.getByRole('option').first()).toBeVisible()
    await page.getByRole('button', { name: 'Outside' }).click()
    expect(await page.evaluate(() => owner.suggestionsOpen)).toBe(false)
    await page.evaluate(() => suggestions.dispose())
    await expect(panel).toHaveCount(0)
})
