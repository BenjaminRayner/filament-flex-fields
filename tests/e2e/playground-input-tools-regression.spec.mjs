import {test, expect} from '@playwright/test'
import {gotoPlaygroundPage} from './global-setup.mjs'
import {trackConsoleErrors} from './helpers/console-errors.mjs'

const inputOwner = (page, key) => page.locator(`[data-fff-text-input]:has(input[id$="${key}"])`)

async function openToolMeasured(page, button, readySelector) {
    await button.evaluate((element, selector) => {
        window.__toolReadyReceipt = new Promise((resolve, reject) => {
            element.addEventListener('click', () => {
                const started = performance.now()
                const deadline = setTimeout(() => reject(Error('Tool did not become ready: ' + selector)), 45000)
                const inspect = () => {
                    const ready = document.querySelector(selector)
                    if (ready?.getClientRects().length && getComputedStyle(ready).visibility === 'visible') {
                        requestAnimationFrame(() => {clearTimeout(deadline); resolve(performance.now() - started)})
                    } else requestAnimationFrame(inspect)
                }
                requestAnimationFrame(inspect)
            }, {capture: true, once: true})
        })
    }, readySelector)
    await button.click()
    return page.evaluate(() => window.__toolReadyReceipt)
}

test('actual emoji library and associated icon picker preserve input text, caret edits and independent state', async ({page}, info) => {
    test.setTimeout(120000)
    const {assertClean} = trackConsoleErrors(page)
    await gotoPlaygroundPage(page, 'flex-text-input')
    const owner = inputOwner(page, 'flex_text_input__basic')
    const input = owner.locator('input').first()
    await expect(input).toBeVisible()
    await input.click()
    await input.fill('Hello ')
    await input.press('End')
    const firstEmojiReadyMs = await openToolMeasured(page, owner.locator('.fff-flex-text-input__action-btn--emoji'),
        '.fff-emoji-picker__panel .fff-emoji-picker__host[aria-busy="false"]')
    const panel = page.locator('.fff-emoji-picker__panel:visible')
    const picker = panel.locator('emoji-picker')
    await expect(panel.locator('.fff-emoji-picker__host')).toHaveAttribute('aria-busy', 'false', {timeout: 45000})
    const emoji = picker.locator('.emoji-menu button.emoji:visible').first()
    await expect(emoji).toBeVisible()
    const unicode = await emoji.innerText()
    await emoji.click()
    await expect(input).toHaveValue('Hello ' + unicode)
    await expect(panel).toHaveCount(0)
    const reopenEmojiReadyMs = await openToolMeasured(page, owner.locator('.fff-flex-text-input__action-btn--emoji'),
        '.fff-emoji-picker__panel .fff-emoji-picker__host[aria-busy="false"]')
    await expect(panel.locator('.fff-emoji-picker__host')).toHaveAttribute('aria-busy', 'false')
    await page.keyboard.press('Escape')
    await expect(panel).toHaveCount(0)
    await page.getByRole('tab', {name: 'Emoji & dictation', exact: true}).click()
    const iconOwner = inputOwner(page, 'flex_text_input__curated_icon')
    const text = iconOwner.locator('input').first()
    const before = await text.inputValue()
    const iconFirstReadyMs = await openToolMeasured(page, iconOwner.getByRole('button', {name: 'Choose curated icon', exact: true}),
        '.fff-icon-select__menu [role="option"] .fff-icon-select__glyph svg')
    const iconMenu = page.locator('.fff-icon-select__menu:visible')
    const icon = iconMenu.locator('[role="option"][data-value][aria-selected="false"]:not([disabled])').first()
    await expect(icon).toBeVisible()
    const name = await icon.getAttribute('data-value')
    await icon.click()
    await expect(text).toHaveValue(before)
    await expect(iconOwner.locator('.fff-icon-select__preview svg').first()).toBeVisible()
    const iconState = await iconOwner.locator('.fff-icon-select').evaluate(el => el._x_dataStack[0].state)
    expect(iconState).toBe(name)
    await expect(iconMenu).toHaveCount(0)
    const iconReopenReadyMs = await openToolMeasured(page, iconOwner.getByRole('button', {name: 'Choose curated icon', exact: true}),
        '.fff-icon-select__menu [role="option"] .fff-icon-select__glyph svg')
    await page.keyboard.press('Escape')
    await expect(iconMenu).toHaveCount(0)
    await page.getByRole('tab', {name: 'Start here', exact: true}).click()
    await expect(input).toHaveValue('Hello ' + unicode)
    await info.attach('input-tools.json', {body: JSON.stringify({actualEmojiLibrary: true, firstEmojiReadyMs, reopenEmojiReadyMs,
        iconFirstReadyMs, iconReopenReadyMs, chosenIcon: name, originalTextPreserved: true}), contentType: 'application/json'})
    await page.screenshot({path: info.outputPath('input-tools.png')})
    assertClean()
})

test('dictation integration commits final results once, handles denied permission and cancels when its owner deactivates', async ({page}, info) => {
    test.setTimeout(90000)
    const {assertClean} = trackConsoleErrors(page)
    await page.addInitScript(() => {
        window.__dictationFixture = {sessions: [], aborted: 0}
        class FixtureRecognition {
            constructor() {window.__dictationFixture.sessions.push(this)}
            start() {this.started = true}
            stop() {this.onend?.()}
            abort() {window.__dictationFixture.aborted++}
        }
        window.SpeechRecognition = FixtureRecognition
        window.webkitSpeechRecognition = FixtureRecognition
    })
    await gotoPlaygroundPage(page, 'flex-text-input')
    const owner = inputOwner(page, 'flex_text_input__basic')
    const input = owner.locator('input').first()
    const microphone = owner.locator('.fff-flex-text-input__action-btn--dictation')
    await expect.poll(() => owner.evaluate(el => el._fffFactoryReady === true)).toBe(true)
    await input.click()
    await input.fill('Hello')
    await microphone.click()
    await expect(microphone).toHaveAttribute('aria-pressed', 'true')
    await page.evaluate(() => {
        const session = window.__dictationFixture.sessions.at(-1)
        const result = Object.assign([{transcript: 'world'}], {isFinal: true})
        session.onresult({resultIndex: 0, results: [result]})
        session.onresult({resultIndex: 0, results: [result]})
    })
    await microphone.click()
    await expect(input).toHaveValue('Hello world')
    await expect(microphone).toHaveAttribute('aria-pressed', 'false')
    await microphone.click()
    await page.evaluate(() => window.__dictationFixture.sessions.at(-1).onerror({error: 'not-allowed'}))
    await expect(owner.locator('.fff-flex-text-input__status')).toBeVisible()
    await expect(input).toHaveValue('Hello world')
    await microphone.click()
    await expect(microphone).toHaveAttribute('aria-pressed', 'true')
    await page.getByRole('tab', {name: 'Native compatibility', exact: true}).click()
    await expect.poll(() => page.evaluate(() => window.__dictationFixture.aborted)).toBeGreaterThan(0)
    await page.getByRole('tab', {name: 'Start here', exact: true}).click()
    await expect(input).toHaveValue('Hello world')
    await expect(microphone).toHaveAttribute('aria-pressed', 'false')
    await info.attach('dictation-scope.json', {body: JSON.stringify({controlledRecognizer: true, actualMicrophone: false,
        finalResultExactlyOnce: true, deniedPermission: true, inactiveOwnerCancelled: true}), contentType: 'application/json'})
    assertClean()
})

test('text typed into the native SSR control survives delayed FLEX module initialization', async ({page, baseURL}, info) => {
    test.setTimeout(45000)
    let release
    const gate = new Promise(resolve => {release = resolve})
    let requested = false
    await page.route('**/components/flex-text-input.js*', async route => {
        requested = true
        await gate
        await route.continue()
    })
    const origin = new URL(baseURL).origin
    try {
        await page.goto(origin + '/admin/field-performance/flex?per=1&workload=inputs', {waitUntil: 'domcontentloaded'})
        const input = page.locator('input[id$="input_plain_1"]')
        await expect(input).toBeVisible()
        await expect.poll(() => requested).toBe(true)
        const owner = input.locator('xpath=ancestor::*[@data-fff-text-input][1]')
        expect(await owner.evaluate(el => el._fffFactoryReady === true)).toBe(false)
        await input.click()
        await input.press('End')
        await input.pressSequentially(' typed before ready')
        const expected = await input.inputValue()
        expect(expected).toBe('Example value 1 typed before ready')
        release()
        await expect.poll(() => owner.evaluate(el => el._fffFactoryReady === true)).toBe(true)
        await expect(input).toHaveValue(expected)
        await expect.poll(() => input.evaluate(el => {
            const component = window.Livewire.find(el.closest('[wire\\:id]').getAttribute('wire:id'))
            return component.get('data.input_plain_1')
        })).toBe(expected)
        await info.attach('early-native-typing.json', {body: JSON.stringify({heldModule: true, actualKeyboard: true, preserved: true}), contentType: 'application/json'})
    } finally {
        release()
    }
})
