import assert from 'node:assert/strict'

/** Real native Action responses, installed Alpine and independent lazy menus. */
export async function verifyNativeSelectPortals(page, origin) {
    // Finish the preceding SPA's native background requests before this probe
    // deliberately replaces its document; keep all request-failure assertions.
    await page.waitForLoadState('networkidle')
    let release, requested = false
    const held = new Promise(resolve => { release = resolve })
    const holdModule = async route => { requested = true; await held; await route.continue() }
    await page.route('**/components/select-field.js', holdModule)
    try {
        await page.goto(origin + '/cost?case=portal-page', {waitUntil: 'domcontentloaded'})
        const trigger = page.getByRole('button', {name: 'Page select 1', exact: true})
        await trigger.waitFor({state: 'visible'})
        await page.waitForFunction(() => !!window.FffAssetInjector)
        assert.equal(await page.evaluate(() => [...document.querySelectorAll('.fff-select-field__interactive')]
            .some(el => el._fffFactoryReady === true)), false)
        // Actual browser hit-testing, not HTMLElement.click() or a replay flag.
        const rect = await trigger.boundingBox()
        assert.ok(rect)
        await page.mouse.click(rect.x + rect.width / 2, rect.y + rect.height / 2)
        await page.waitForFunction(() => [...document.querySelectorAll('[data-fff-open-trigger]')]
            .some(el => el.getAttribute('aria-busy') === 'true'))
        release()
        await page.getByRole('listbox', {name: 'Page select 1', exact: true}).waitFor({state: 'visible'})
        assert.equal(requested, true)
        await page.getByRole('option', {name: 'Beta', exact: true}).click()
        assert.match(await trigger.innerText(), /Beta/)
    } finally {
        release()
        await page.unroute('**/components/select-field.js', holdModule)
    }
    await page.goto(origin + '/cost?case=portal')
    await page.getByRole('button', {name: 'Open first FLEX field', exact: true}).click()
    await page.getByRole('button', {name: 'Parent select 1', exact: true}).waitFor({state: 'visible'})
    await page.waitForFunction(() => [...document.querySelectorAll('.fff-select-field__interactive')]
        .length === 2 && [...document.querySelectorAll('.fff-select-field__interactive')].every(el => el._fffFactoryReady === true))
    assert.equal(await page.locator('[data-fff-portal-template-id]').count(), 2)
    assert.equal(await page.locator('[data-fff-portal-template-definition]').count(), 1)
    assert.equal(await page.getByRole('listbox').count(), 0)
    await page.getByRole('button', {name: 'Parent select 1', exact: true}).click()
    const search = page.locator('.fff-select-headless-menu input[role="combobox"]:visible')
    await search.fill('Beta')
    await page.getByRole('option', {name: 'Beta', exact: true}).click()
    assert.match(await page.getByRole('button', {name: 'Parent select 1', exact: true}).innerText(), /Beta/)
    await page.getByRole('button', {name: 'Open child FLEX field', exact: true}).click()
    await page.getByRole('button', {name: 'Child select 1', exact: true}).click()
    await search.fill('Gamma')
    await page.getByRole('option', {name: 'Gamma', exact: true}).waitFor({state: 'visible'})
    assert.equal(await page.evaluate(() => {
        const menu = [...document.querySelectorAll('[role="listbox"]')].find(el => el.getClientRects().length)?.closest('.fi-modal')
        const trigger = [...document.querySelectorAll('button')].find(el => el.getAttribute('aria-expanded') === 'true'
            && el.textContent.includes('Alpha') && el.closest('.fi-modal'))
        return !!menu && menu === trigger?.closest('.fi-modal') && menu.contains(document.activeElement)
    }), true, 'Late child menu escaped its native focus trap')
    await page.getByRole('option', {name: 'Gamma', exact: true}).click()
    await page.getByRole('button', {name: 'Cancel', exact: true}).last().click()
    const parent = page.getByRole('button', {name: 'Parent select 1', exact: true})
    await parent.waitFor({state: 'visible'})
    assert.match(await parent.innerText(), /Beta/, 'Closing child lost the suspended parent selection')
    await page.getByRole('button', {name: 'Parent select 2', exact: true}).click()
    await search.fill('Gamma')
    await page.getByRole('option', {name: 'Gamma', exact: true}).click()
    assert.match(await parent.innerText(), /Beta/, 'A sibling changed the first owner')
    await page.getByRole('button', {name: 'Cancel', exact: true}).click()
    await page.waitForFunction(() => document.querySelectorAll('.fff-select-field__interactive').length === 0
        && window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().length === 0)
    assert.equal(await page.getByRole('listbox').count(), 0)
    await page.getByRole('button', {name: 'Open first FLEX field', exact: true}).click()
    await parent.waitFor({state: 'visible'})
    assert.match(await parent.innerText(), /Alpha/, 'Reopening reused a retired owner selection')
    await parent.click()
    await search.fill('Beta')
    await page.getByRole('option', {name: 'Beta', exact: true}).click()
    await page.getByRole('button', {name: 'Cancel', exact: true}).click()
    await page.waitForFunction(() => window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().length === 0)
    return {passed: true, earlyClickWithHeldModule: true, independentOwners: 2, lazyClosedMenus: true, childFocusTrap: true,
        suspendedParentPreserved: true, reopenFresh: true, remainingConsumers: 0}
}
