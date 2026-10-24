import fs from 'node:fs'
import {test, expect} from '@playwright/test'
import {gotoPlaygroundPage} from './global-setup.mjs'
import {SELECT_PLAYGROUND_E2E_VARIANTS} from './fixtures/select-playground-variants.mjs'
import {trackConsoleErrors} from './helpers/console-errors.mjs'

const source = fs.readFileSync(new URL('../../src/Support/Playground/SelectPlayground.php', import.meta.url), 'utf8')
// A field may intentionally have no default (for example native modal create).
// Inventory rendered declarations, rather than treating defaultState as a schema.
const expectedKeys = [...source.matchAll(/SelectField::make\('(select__\w+)'\)/g)].map(match => match[1]).sort()
const groups = ['Overview', 'Sizes & surfaces', 'Configuration', 'Create options', 'Scale & RTL', 'Native API']
const metadata = new Map(SELECT_PLAYGROUND_E2E_VARIANTS.map(item => [item.key, item]))
const fieldSelector = key => `.fff-select-field__interactive:has(button[id$="${key}"]), .fff-select-field__interactive:has(input[id$="${key}"])`

async function activateGroup(page, name) {
    const tab = page.getByRole('tab', {name, exact: true})
    await tab.evaluate(el => {
        window.__previousTabAncestors = []
        for (let node = el.closest('.fff-segment-tabs'); node; node = node.parentElement) window.__previousTabAncestors.push(node)
    })
    await tab.click()
    await expect(tab).toHaveAttribute('aria-selected', 'true')
    const panel = page.locator(`[role="tabpanel"][aria-labelledby="${await tab.getAttribute('id')}"]`)
    await expect(panel).toBeVisible()
    try {
        await expect.poll(() => panel.locator('button.fi-select-input-btn:visible, select.fi-select-input:visible').count(), {timeout: 20000}).toBeGreaterThan(0)
    } catch (error) {
        console.log('Deferred tab failure', name, JSON.stringify(await page.evaluate(() => ({
            events: window.__tabEvents, tabs: [...document.querySelectorAll('.fff-segment-tabs')].map(el => ({id: el.id, tab: el._x_dataStack?.[0]?.tab})),
            ancestors: window.__previousTabAncestors?.map(el => ({tag: el.tagName, classes: el.className, key: el.getAttribute('wire:key'), connected: el.isConnected})),
            panels: [...document.querySelectorAll('.fff-segment-tabs__panel')].map(el => ({id: el.id, classes: el.className, controls: el.querySelectorAll('.fi-select-input-btn').length})),
        }))))
        throw error
    }
    await expect.poll(() => panel.locator('.fff-select-field__interactive').evaluateAll(elements =>
        elements.filter(el => el.getClientRects().length).every(el => el._fffFactoryReady === true))).toBe(true)
    return panel
}

async function openMeasured(page, trigger, owner, menuId) {
    await trigger.scrollIntoViewIfNeeded()
    await trigger.evaluate((element, id) => {
        window.__catalogOpenReceipt = new Promise((resolve, reject) => {
            const clicked = () => {
                const started = performance.now()
                window.__catalogOpenStateReceipt = null
                element.addEventListener('click', () => {
                    const data = element.closest('.fff-select-field__interactive')?._x_dataStack?.[0]
                    window.__catalogOpenStateReceipt = {committed: data?.comboboxOpen === true, ms: performance.now() - started}
                }, {once: true})
                const deadline = setTimeout(() => reject(Error('Menu failed to become ready: ' + id)), 10000)
                const inspect = () => {
                    const menu = document.getElementById(id)
                    const data = element.closest('.fff-select-field__interactive')?._x_dataStack?.[0]
                    if (menu && data?.comboboxOpen && data.menuReady && menu.getClientRects().length
                        && Number(getComputedStyle(menu).opacity) > .99) {
                        requestAnimationFrame(() => {clearTimeout(deadline); resolve(performance.now() - started)})
                    } else requestAnimationFrame(inspect)
                }
                requestAnimationFrame(inspect)
            }
            element.addEventListener('click', clicked, {once: true, capture: true})
        })
    }, menuId)
    // One real click only: no forced clicks, direct handler calls or retry opens.
    const box = await trigger.boundingBox()
    const rtl = await trigger.evaluate(el => getComputedStyle(el).direction === 'rtl')
    await trigger.click({position: {x: rtl ? Math.min(12, box.width / 2) : Math.max(box.width / 2, box.width - 12), y: box.height / 2}})
    const elapsed = await page.evaluate(() => window.__catalogOpenReceipt)
    await expect.poll(() => owner.evaluate(el => el._x_dataStack[0].comboboxOpen)).toBe(true)
    return elapsed
}

async function close(page, owner) {
    await page.keyboard.press('Escape')
    await expect.poll(() => owner.evaluate(el => el._x_dataStack[0].comboboxOpen)).toBe(false)
    await expect(page.locator('.fff-select-headless-menu:visible')).toHaveCount(0)
}

for (const profile of [{name: 'desktop', width: 1440}, {name: 'mobile', width: 390}]) {
    test(`all declared Select examples open, reopen and update independent native state · ${profile.name}`, async ({page}, info) => {
        test.setTimeout(360000)
        page.setDefaultTimeout(15000)
        await page.setViewportSize({width: profile.width, height: 1000})
        const {assertClean} = trackConsoleErrors(page)
        const results = [], seen = new Set()
        await page.addInitScript(() => {
            window.__tabEvents = []
            for (const type of ['reset-schema-component-state', 'fff-segment-tab-change']) window.addEventListener(type, event =>
                window.__tabEvents.push({type, detail: event.detail, at: performance.now()}))
        })
        await gotoPlaygroundPage(page, 'select-field')
        expect(await page.locator('template[data-fff-plan-definition], template[data-fff-portal-template-definition]')
            .evaluateAll(elements => elements.length > 0 && elements.every(el => el.getAttribute('wire:key')?.startsWith('fff-')))).toBe(true)
        for (const group of groups) {
            const panel = await activateGroup(page, group)
            const keys = await panel.locator('button.fi-select-input-btn:visible, select.fi-select-input:visible').evaluateAll(elements =>
                elements.map(el => (el.id || el.querySelector('input')?.id || '').match(/select__\w+$/)?.[0]).filter(Boolean))
            for (const key of keys) {
                console.log('Catalog example:', profile.name, group, key)
                expect(seen.has(key), 'Duplicate catalog example: ' + key).toBe(false)
                seen.add(key)
                const native = page.locator(`select.fi-select-input[id$="${key}"]`)
                if (await native.count()) {
                    const option = native.locator('option:not([disabled])').filter({hasNotText: /^\s*$/}).last()
                    const value = await option.getAttribute('value')
                    await native.selectOption(value)
                    if (await native.evaluate(el => el.multiple)) await expect(native).toHaveValues([value])
                    else await expect(native).toHaveValue(value)
                    results.push({key, group, native: true})
                    continue
                }
                const owner = page.locator(fieldSelector(key)).first()
                const trigger = owner.locator('button.fi-select-input-btn').first()
                const config = await owner.evaluate(el => JSON.parse(el.querySelector(':scope > template[data-fff-select-configuration]').content.textContent))
                if (config.disabled) {
                    await expect(trigger).toBeDisabled()
                    results.push({key, group, disabled: true})
                    continue
                }
                const firstMs = await openMeasured(page, trigger, owner, config.menuDomId)
                const menu = page.locator(`[id="${config.menuDomId}"]`)
                const allSelected = await owner.evaluate(el => {
                    const data = el._x_dataStack[0]
                    return data.multiple && !data.keepSelectedOptionsInDropdown && data.flatOptions.length > 0
                        && data.flatOptions.every(option => data.comboboxSelectedValues.map(String).includes(String(option.value)))
                })
                if (allSelected) {
                    await expect(menu.locator('[role="option"]:visible')).toHaveCount(0)
                    await close(page, owner)
                    await trigger.locator('.fi-badge-delete-btn').first().click()
                    await openMeasured(page, trigger, owner, config.menuDomId)
                }
                const query = metadata.get(key)?.searchQuery
                if (query || config.inlineSearch) {
                    // Mobile sheets expose their own search even for inline
                    // triggers; the original page control is correctly inert.
                    const menuSearch = menu.locator('input[role="combobox"]:visible')
                    const search = await menuSearch.count() ? menuSearch : trigger.locator('input:visible')
                    await search.fill(query || '')
                }
                const options = menu.locator('[role="option"][data-value][aria-selected="false"]:not([disabled]):visible')
                await expect(options.first()).toBeVisible()
                const option = options.first()
                const value = await option.getAttribute('data-value')
                const wasSelected = await option.getAttribute('aria-selected') === 'true'
                await option.click()
                await expect.poll(() => owner.evaluate((el, candidate) => el._x_dataStack[0].comboboxSelectedValues.map(String).includes(candidate), value), {message: key + ' selection ' + value})
                    .toBe(!(config.multiple && wasSelected))
                const values = await owner.evaluate(el => el._x_dataStack[0].comboboxSelectedValues.map(String))
                if (config.multiple && wasSelected) expect(values).not.toContain(value)
                else expect(values).toContain(value)
                const wire = await owner.evaluate(el => {
                    const data = el._x_dataStack[0]
                    return window.Livewire.find(data.livewireId).get(data.statePath)
                })
                expect(Array.isArray(wire) ? wire.map(String) : [String(wire)]).toEqual(values)
                if (await owner.evaluate(el => el._x_dataStack[0].comboboxOpen)) await close(page, owner)
                const reopenMs = await openMeasured(page, trigger, owner, config.menuDomId)
                await close(page, owner)
                results.push({key, group, firstMs, reopenMs, async: config.hasDynamicSearchResults || config.hasDynamicOptions,
                    multiple: config.multiple, selected: values})
                assertClean()
            }
            await info.attach(`completed-${group.replaceAll(/[^a-z0-9]+/gi, '-')}.json`, {body: JSON.stringify(results.filter(row => row.group === group)), contentType: 'application/json'})
        }
        expect([...seen].sort()).toEqual(expectedKeys)
        expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)).toBe(false)
        await info.attach(`select-catalog-${profile.name}.json`, {body: JSON.stringify({profile, results}, null, 2), contentType: 'application/json'})
        await page.screenshot({path: info.outputPath(`select-catalog-${profile.name}.png`)})
        assertClean()
    })
}

test('cursor pagination reaches its final page and 10k list/grid virtualization reaches the last real option', async ({page}, info) => {
    test.setTimeout(120000)
    const {assertClean} = trackConsoleErrors(page)
    await gotoPlaygroundPage(page, 'select-field')
    const paged = page.locator(fieldSelector('select__async_paginated')).first()
    await page.waitForFunction(() => [...document.querySelectorAll('.fff-select-field__interactive')]
        .filter(el => el.getClientRects().length).every(el => el._fffFactoryReady === true))
    const config = await paged.evaluate(el => JSON.parse(el.querySelector(':scope > template[data-fff-select-configuration]').content.textContent))
    await openMeasured(page, paged.locator('.fi-select-input-btn'), paged, config.menuDomId)
    const menu = page.locator(`[id="${config.menuDomId}"]`)
    await expect.poll(() => paged.evaluate(el => el._x_dataStack[0].flatOptions.length)).toBeGreaterThan(0)
    const cursors = []
    for (let step = 0; step < 12; step++) {
        const state = await paged.evaluate(el => {const data = el._x_dataStack[0]; return {
            count: data.flatOptions.length, more: data.searchResultsHasMore,
            pending: data.searchPending || data.loadingMore, cursor: data.searchResultsCursor,
        }})
        if (state.pending) {
            await expect.poll(() => paged.evaluate(el => !!(el._x_dataStack[0].searchPending || el._x_dataStack[0].loadingMore))).toBe(false)
            continue
        }
        if (!state.more) break
        cursors.push(state.cursor)
        await menu.locator('[x-ref="headlessOptionsList"]').hover()
        await page.mouse.wheel(0, 1100)
        await expect.poll(() => paged.evaluate((el, count) => {
            const data = el._x_dataStack[0]
            return data.flatOptions.length > count || !data.searchResultsHasMore || !!data.searchError
        }, state.count)).toBe(true)
    }
    const all = await paged.evaluate(el => {const data = el._x_dataStack[0]; return {
        values: data.flatOptions.map(option => String(option.value)), more: data.searchResultsHasMore,
        error: !!data.searchError, pending: data.loadingMore,
    }})
    expect(all.more).toBe(false)
    expect(all.error).toBe(false)
    expect(all.pending).toBe(false)
    expect(all.values.length).toBeGreaterThan(24)
    expect(new Set(all.values).size).toBe(all.values.length)
    expect(new Set(cursors).size).toBe(cursors.length)
    await close(page, paged)
    await page.getByRole('tab', {name: 'Scale & RTL', exact: true}).click()
    const windows = []
    for (const [key, lastValue] of [['select__scale_10k', 'opt_10000'], ['select__scale_grid', 'grid_1000']]) {
        const owner = page.locator(fieldSelector(key)).first()
        await expect(owner).toBeVisible()
        await expect.poll(() => owner.evaluate(el => el._fffFactoryReady === true)).toBe(true)
        const boot = await owner.evaluate(el => JSON.parse(el.querySelector(':scope > template[data-fff-select-configuration]').content.textContent))
        await openMeasured(page, owner.locator('.fi-select-input-btn'), owner, boot.menuDomId)
        const panel = page.locator(`[id="${boot.menuDomId}"]`)
        try {
            await expect.poll(() => panel.locator('[role="option"]:visible').count()).toBeGreaterThan(0)
        } catch (error) {
            console.log('Virtualization failure', key, JSON.stringify(await owner.evaluate(el => {
                const d = el._x_dataStack[0]
                return {options: d.flatOptions.length, open: d.comboboxOpen, ready: d.menuReady,
                    disposed: d._selectDisposed, query: d.comboboxQuery, error: d.searchError,
                    rows: d.visibleFlatRows?.length, height: d._virtualViewportHeight,
                    tab: el.closest('.fff-segment-tabs')?._x_dataStack?.[0]?.tab}
            })))
            throw error
        }
        const rendered = await panel.locator('[role="option"]:visible').count()
        expect(rendered).toBeGreaterThan(0)
        expect(rendered).toBeLessThan(100)
        await panel.locator('[x-ref="headlessOptionsList"]').hover()
        await page.mouse.wheel(0, 1000000)
        const last = panel.locator(`[role="option"][data-value="${lastValue}"]`)
        await expect(last).toBeVisible()
        await last.click()
        await expect.poll(() => owner.evaluate(el => el._x_dataStack[0].comboboxSelectedValues.map(String))).toContain(lastValue)
        windows.push({key, rendered, lastValue})
    }
    await info.attach('pagination-virtualization.json', {body: JSON.stringify({pages: cursors.length + 1, total: all.values.length, cursors, windows}, null, 2), contentType: 'application/json'})
    assertClean()
})

test('plain labels stay literal while explicitly allowed option HTML remains rendered', async ({page}) => {
    const source = fs.readFileSync(new URL('../../resources/views/forms/components/partials/select-field/option-label.blade.php', import.meta.url), 'utf8')
    const expression = source.match(/x-text="(optionDropdownLabel\(row\.option\))"/)?.[1]
    expect(expression).toBeTruthy()
    await gotoPlaygroundPage(page, 'select-field')
    await page.waitForFunction(() => document.querySelector('.fff-select-field__interactive')?._fffFactoryReady === true)
    const results = await page.evaluate(async expression => {
        const owner = document.querySelector('.fff-select-field__interactive')._x_dataStack[0]
        const labels = ['A & B < C > D "quoted"', '&amp; &lt;b&gt;', '<img src=x onerror="window.__labelExecuted=true">', "Łódź 🇵🇱 'single'", 'Line\r\nbreak', 'Null\0byte']
        const results = []
        for (const label of labels) {
            const span = document.createElement('span')
            span.setAttribute('x-text', expression)
            Alpine.addScopeToNode(span, {row: {option: label}, isHtmlAllowed: false,
                optionDropdownLabel: option => option, escapeHtml: owner.escapeHtml.bind(owner)})
            document.body.append(span)
            Alpine.initTree(span)
            await Alpine.nextTick()
            results.push({label, text: span.textContent, children: span.childElementCount})
            Alpine.destroyTree(span)
            span.remove()
        }
        const span = document.createElement('span')
        span.setAttribute('x-html', expression)
        Alpine.addScopeToNode(span, {row: {option: '<b>Allowed label</b>'}, isHtmlAllowed: true,
            optionDropdownLabel: option => option, escapeHtml: owner.escapeHtml.bind(owner)})
        document.body.append(span)
        Alpine.initTree(span)
        await Alpine.nextTick()
        const allowed = span.querySelector('b')?.textContent
        Alpine.destroyTree(span)
        span.remove()
        return {results, allowed, executed: window.__labelExecuted === true}
    }, expression)
    for (const row of results.results) {
        expect(row.text).toBe(row.label)
        expect(row.children).toBe(0)
    }
    expect(results.allowed).toBe('Allowed label')
    expect(results.executed).toBe(false)
})

test('300 active fields retain 150 independent Select owners and respond at both ends of every workload', async ({page, baseURL}, info) => {
    test.setTimeout(120000)
    const {assertClean} = trackConsoleErrors(page)
    await page.setViewportSize({width: 1440, height: 1000})
    await page.goto(new URL(baseURL).origin + '/admin/field-performance/flex', {waitUntil: 'domcontentloaded'})
    await expect(page.locator('[data-fff-text-input]')).toHaveCount(150)
    await expect(page.locator('.fff-select-field__interactive')).toHaveCount(150)
    await page.waitForFunction(() => [...document.querySelectorAll('[data-fff-text-input], .fff-select-field__interactive')]
        .every(el => el._fffFactoryReady === true))
    await page.evaluate(() => {
        window.__closedSelectOutsideLookups = 0
        for (const element of document.querySelectorAll('.fff-select-field__interactive')) {
            const data = element._x_dataStack[0]
            const original = data.isEventInsideHeadlessMenu
            data.isEventInsideHeadlessMenu = function (...args) {
                if (!this.comboboxOpen) window.__closedSelectOutsideLookups++
                return original.apply(this, args)
            }
        }
    })
    const samples = []
    for (const variant of ['plain', 'search', 'multiple', 'grouped', 'large', 'async']) {
        for (const index of [1, 25]) {
            const key = `select_${variant}_${index}`
            const owner = page.locator(fieldSelector(key)).first()
            const trigger = owner.locator('.fi-select-input-btn')
            const config = await owner.evaluate(el => JSON.parse(el.querySelector(':scope > template[data-fff-select-configuration]').content.textContent))
            const firstMs = await openMeasured(page, trigger, owner, config.menuDomId)
            const firstCommit = await page.evaluate(() => window.__catalogOpenStateReceipt)
            expect(firstCommit?.committed, 'Open must commit during the native click: ' + key).toBe(true)
            const menu = page.locator(`[id="${config.menuDomId}"]`)
            if (variant === 'async') await menu.locator('input[role="combobox"]').fill('Option 0500')
            const option = menu.locator('[role="option"][data-value][aria-selected="false"]:not([disabled]):visible').first()
            await expect(option).toBeVisible()
            const value = await option.getAttribute('data-value')
            await option.click()
            await expect.poll(() => owner.evaluate(el => el._x_dataStack[0].comboboxSelectedValues.map(String))).toContain(value)
            if (await owner.evaluate(el => el._x_dataStack[0].comboboxOpen)) await close(page, owner)
            const reopenMs = await openMeasured(page, trigger, owner, config.menuDomId)
            const reopenCommit = await page.evaluate(() => window.__catalogOpenStateReceipt)
            expect(reopenCommit?.committed, 'Reopen must commit during the native click: ' + key).toBe(true)
            await close(page, owner)
            samples.push({key, firstMs, reopenMs, firstCommit, reopenCommit})
        }
    }
    expect(await page.locator('.fff-select-field__interactive').evaluateAll(elements =>
        new Set(elements.map(el => el._x_dataStack[0]._engine)).size)).toBe(150)
    expect(await page.evaluate(() => window.__closedSelectOutsideLookups),
        'Closed Select owners must not resolve their absent menu on unrelated clicks').toBe(0)
    const outsideOwner = page.locator(fieldSelector('select_search_1')).first()
    const outsideTrigger = outsideOwner.locator('.fi-select-input-btn')
    const outsideMenuId = await outsideOwner.evaluate(el => el._x_dataStack[0].menuDomId)
    await openMeasured(page, outsideTrigger, outsideOwner, outsideMenuId)
    await page.evaluate(() => {
        const button = document.createElement('button')
        button.type = 'button'
        button.id = 'fff-regression-outside'
        button.textContent = 'Outside test control'
        Object.assign(button.style, {position: 'fixed', right: '16px', top: '16px', zIndex: '999999'})
        document.body.append(button)
    })
    await page.locator('#fff-regression-outside').click()
    await expect.poll(() => outsideOwner.evaluate(el => el._x_dataStack[0].comboboxOpen)).toBe(false)
    await expect(outsideOwner).toHaveAttribute('data-fff-dropdown-close-reason', 'outside')
    await page.locator('#fff-regression-outside').evaluate(el => el.remove())
    expect(await page.evaluate(() => window.__closedSelectOutsideLookups)).toBe(0)
    await info.attach('300-field-open-latency.json', {body: JSON.stringify({fields: 300, samples}, null, 2), contentType: 'application/json'})
    await page.screenshot({path: info.outputPath('300-fields.png')})
    assertClean()
})

test('native create modal, inline creation, chip reorder and dependent options preserve native state', async ({page}, info) => {
    test.setTimeout(120000)
    page.setDefaultTimeout(15000)
    const {assertClean} = trackConsoleErrors(page)
    const read = owner => owner.evaluate(el => {
        const data = el._x_dataStack[0]
        return window.Livewire.find(data.livewireId).get(data.statePath)
    })
    const choose = async (key, value) => {
        const owner = page.locator(fieldSelector(key)).first()
        await expect.poll(() => owner.evaluate(el => el._fffFactoryReady === true)).toBe(true)
        const config = await owner.evaluate(el => JSON.parse(el.querySelector(':scope > template[data-fff-select-configuration]').content.textContent))
        await openMeasured(page, owner.locator('.fi-select-input-btn'), owner, config.menuDomId)
        await page.locator(`[id="${config.menuDomId}"] [role="option"][data-value="${value}"]`).click()
        await expect.poll(() => read(owner)).toBe(value)
        return owner
    }
    await gotoPlaygroundPage(page, 'select-field')
    const reorder = page.locator(fieldSelector('select__reorderable')).first()
    await expect.poll(() => reorder.evaluate(el => el._fffFactoryReady === true)).toBe(true)
    const before = await read(reorder)
    const chips = reorder.locator('.fi-badge.fi-reorderable')
    await chips.first().scrollIntoViewIfNeeded()
    const from = await chips.first().boundingBox(), to = await chips.last().boundingBox()
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2)
    await page.mouse.down()
    await page.mouse.move(to.x + to.width - 2, to.y + to.height / 2, {steps: 12})
    await page.mouse.up()
    await expect.poll(() => read(reorder)).not.toEqual(before)
    expect([...(await read(reorder))].sort()).toEqual([...before].sort())
    await activateGroup(page, 'Create options')
    const created = page.locator(fieldSelector('select__create_single')).first()
    const config = await created.evaluate(el => JSON.parse(el.querySelector(':scope > template[data-fff-select-configuration]').content.textContent))
    await openMeasured(page, created.locator('.fi-select-input-btn'), created, config.menuDomId)
    const menu = page.locator(`[id="${config.menuDomId}"]`)
    const label = 'Browser regression framework'
    await menu.locator('input[role="combobox"]').fill(label)
    await menu.locator('.fff-select-smart-create:visible').click()
    await expect.poll(() => read(created)).toBe(label)
    const modalOwner = page.locator(fieldSelector('select__modal_create')).first()
    const wrapper = modalOwner.locator('xpath=ancestor::*[contains(concat(" ",normalize-space(@class)," ")," fi-input-wrp ")][1]')
    await wrapper.getByRole('button', {name: 'Create', exact: true}).click()
    const modal = page.getByRole('dialog', {name: 'Create', exact: true})
    await modal.getByRole('textbox', {name: 'Name*', exact: true}).fill('Browser test item')
    await modal.getByRole('textbox', {name: 'Code*', exact: true}).fill('browser-test')
    await modal.getByRole('button', {name: 'Create', exact: true}).click()
    await expect.poll(() => read(modalOwner)).toMatch(/^created-browser-test-/)
    await expect(modal).not.toBeVisible()
    await activateGroup(page, 'Scale & RTL')
    await choose('select__cascade_country', 'pl')
    const region = await choose('select__cascade_region', 'mz')
    await choose('select__cascade_country', 'us')
    await expect.poll(() => read(region)).toBe(null)
    await choose('select__cascade_region', 'ca')
    await region.locator('.fi-select-input-value-remove-btn').click()
    await expect.poll(() => read(region)).toBe(null)
    await info.attach('creation-cascade-reorder.json', {body: JSON.stringify({actualChipDrag: true, inlineCreate: true,
        nativeCreateModal: true, dependencyReset: true, refreshedDependentOptions: true, clear: true}), contentType: 'application/json'})
    assertClean()
})

test('multiple Select chips keep equal insets in SSR and hydrated size, wrap, RTL and theme variants', async ({page, baseURL}, info) => {
    test.setTimeout(120000)
    const {assertClean} = trackConsoleErrors(page)
    await page.setViewportSize({width: 1440, height: 1000})
    let releaseSelect
    const heldSelect = new Promise(resolve => { releaseSelect = resolve })
    await page.route('**/components/select-field.js', async route => {
        await heldSelect
        await route.continue()
    })
    const measure = async (surface, fullMatrix) => page.evaluate(({surface, fullMatrix}) => {
        const root = document.querySelector('[data-benchmark-field="select-multiple-17"]')
        const frame = root.closest('.fff-select-field') ?? root.querySelector('.fff-select-field')
        const originalClass = frame.className, originalStyle = frame.getAttribute('style'), originalDir = frame.getAttribute('dir')
        const originalDark = document.documentElement.classList.contains('dark')
        const results = []
        try {
            for (const theme of fullMatrix ? ['light', 'dark'] : ['light']) {
                document.documentElement.classList.toggle('dark', theme === 'dark')
                for (const dir of fullMatrix ? ['ltr', 'rtl'] : ['ltr']) {
                    frame.setAttribute('dir', dir)
                    for (const size of ['sm', 'md', 'lg']) {
                        frame.classList.remove('fff-select-field--sm', 'fff-select-field--md', 'fff-select-field--lg')
                        frame.classList.add(`fff-select-field--${size}`)
                        for (const width of [350, 190]) {
                            frame.style.width = `${width}px`
                            frame.style.maxWidth = '100%'
                            const chips = [...root.querySelectorAll(`${surface} .fi-select-input-value-badges-ctn .fi-badge`)]
                            const bounds = frame.getBoundingClientRect(), rects = chips.map(chip => chip.getBoundingClientRect())
                            const top = Math.min(...rects.map(rect => rect.top)), bottom = Math.max(...rects.map(rect => rect.bottom))
                            const start = dir === 'rtl' ? bounds.right - Math.max(...rects.map(rect => rect.right)) : Math.min(...rects.map(rect => rect.left)) - bounds.left
                            const style = getComputedStyle(chips[0])
                            results.push({theme, dir, size, width, viewport: window.innerWidth, frameWidth: bounds.width, height: bounds.height, count: chips.length,
                                rows: new Set(rects.map(rect => Math.round(rect.top))).size,
                                top: top - bounds.top, bottom: bounds.bottom - bottom, start,
                                background: style.backgroundColor, border: style.borderTopColor})
                        }
                    }
                }
            }
        } finally {
            frame.className = originalClass
            if (originalStyle === null) frame.removeAttribute('style'); else frame.setAttribute('style', originalStyle)
            if (originalDir === null) frame.removeAttribute('dir'); else frame.setAttribute('dir', originalDir)
            document.documentElement.classList.toggle('dark', originalDark)
        }
        return results
    }, {surface, fullMatrix})
    const verify = rows => {
        for (const row of rows) {
            const context = JSON.stringify(row)
            expect(row.count, context).toBe(2)
            expect(Math.abs(row.top - row.bottom), context).toBeLessThanOrEqual(.55)
            expect(Math.abs(row.top - row.start), context).toBeLessThanOrEqual(.55)
            expect(row.top, context).toBe({sm: 5, md: 4, lg: 5}[row.size])
            if (row.width === 190) expect(row.rows, context).toBe(2)
            else if (row.viewport >= 1000) expect(row.rows, context).toBe(1)
            else expect(row.rows, context).toBeLessThanOrEqual(2)
            if (row.rows === 1) expect(row.height, context).toBe({sm: 32, md: 36, lg: 42}[row.size])
            expect(row.background, context).toBe(row.theme === 'light' ? 'rgb(250, 250, 250)' : 'rgb(39, 39, 42)')
            expect(row.border, context).toBe(row.theme === 'light' ? 'rgb(228, 228, 231)' : 'rgb(63, 63, 70)')
        }
    }
    let ssr
    try {
        await page.goto(new URL(baseURL).origin + '/admin/field-performance/flex', {waitUntil: 'domcontentloaded'})
        await page.waitForFunction(() => {
            const chip = document.querySelector('[data-benchmark-field="select-multiple-17"] .fff-select-trigger-ssr .fi-badge-label')
            return chip && getComputedStyle(chip).lineHeight === '20px'
        })
        await page.evaluate(() => document.fonts.ready)
        ssr = await measure('.fff-select-trigger-ssr', false)
    } finally { releaseSelect() }
    verify(ssr)
    await page.waitForFunction(() => [...document.querySelectorAll('.fff-select-field__interactive')].every(el => el._fffFactoryReady === true))
    const hydrated = []
    for (const width of [1440, 390]) {
        await page.setViewportSize({width, height: 1000})
        const rows = await measure('.fff-select-field__interactive', true)
        verify(rows)
        hydrated.push({viewport: width, rows})
    }
    await info.attach('multiple-chip-insets.json', {body: JSON.stringify({ssr, hydrated}, null, 2), contentType: 'application/json'})
    await page.setViewportSize({width: 1440, height: 1000})
    await page.locator('[data-benchmark-field="select-multiple-17"]').screenshot({path: info.outputPath('multiple-chips.png')})
    assertClean()
})
