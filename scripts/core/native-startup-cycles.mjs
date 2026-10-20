import assert from 'node:assert/strict'

/** Real native Action + wire:navigate cycles against the isolated SSR host. */
export async function verifyNativeStartupCycles(page, cycles) {
    assert.ok(Number.isSafeInteger(cycles) && cycles >= 1 && cycles <= 100)
    const marker = 'native-startup-' + cycles
    await page.evaluate(marker => {
        const proof = window.__nativeCycleProof = { marker, mounted: 0, retired: 0, navigations: 0, pending: [], timers: new Set() }
        proof.clearTimeout = window.clearTimeout
        window.clearTimeout = function (id) { proof.timers.delete(id); return proof.clearTimeout.call(this, id) }
        document.addEventListener('livewire:navigated', () => { proof.navigations++ })
        proof.capture = () => {
            const element = document.querySelector('[data-fff-text-input]')
            const data = element?._x_dataStack?.[0]
            if (!element?._fffFactoryReady || !data?.runtimeDiagnostics?.initialized) throw Error('Missing ready native field factory')
            if (data.runtimeDiagnostics.disposed || data.runtimeDiagnostics.bindingsDisposed) throw Error('Mounted factory was already disposed')
            proof.pending.push(data); proof.mounted++
            // Exercise a real component-owned status timer, without changing its
            // implementation, destroy hook, Alpine tree or readiness flags.
            const original = window.setTimeout
            window.setTimeout = function (callback, delay, ...args) {
                const id = original.call(this, (...values) => { proof.timers.delete(id); callback(...values) }, delay, ...args)
                proof.timers.add(id); return id
            }
            try { data.showStatus('Lifecycle probe', 60000) } finally { window.setTimeout = original }
            if (proof.timers.size !== 1) throw Error('Native field did not schedule its status timer')
        }
        proof.settled = () => {
            const runtime = window.FffAssetInjector
            return runtime && !runtime.isDisposed() && runtime.getConsumerGraph().getActiveConsumerIds().length === 0
                && runtime.getConsumerGraph().getRetainedResources().length === 0 && runtime.getInflightUrls().length === 0
                && document.querySelectorAll('[data-fff-text-input]').length === 0
                && proof.timers.size === 0 && proof.pending.every(data => data.runtimeDiagnostics.disposed && data.runtimeDiagnostics.bindingsDisposed)
        }
        proof.retire = () => { proof.retired += proof.pending.length; proof.pending.length = 0 }
    }, marker)
    try {
        for (let cycle = 0; cycle < cycles; cycle++) {
            if (cycle > 0) {
                await page.getByRole('button', { name: 'Open first FLEX field', exact: true }).click()
                await page.getByRole('textbox', { name: /^Message/ }).waitFor({ state: 'visible' })
                await page.waitForFunction(() => document.querySelector('[data-fff-text-input]')?._fffFactoryReady === true)
            }
            await page.evaluate(() => window.__nativeCycleProof.capture())
            await page.getByRole('button', { name: 'Cancel', exact: true }).click()
            await page.waitForFunction(() => window.__nativeCycleProof.settled())
            await page.evaluate(() => window.__nativeCycleProof.retire())

            await page.getByRole('link', { name: 'Open field page', exact: true }).click()
            await page.waitForURL('**/cost?case=one')
            await page.waitForFunction(() => document.querySelector('[data-fff-text-input]')?._fffFactoryReady === true)
            assert.equal(await page.locator('[data-fff-asset-consumer]').count(), 1)
            await page.getByRole('textbox', { name: 'Field 0', exact: true }).fill('Native navigation ' + cycle)
            await page.evaluate(() => window.__nativeCycleProof.capture())
            await page.getByRole('link', { name: 'Return to empty page', exact: true }).click()
            await page.waitForURL('**/cost?case=dynamic')
            await page.waitForFunction(() => window.__nativeCycleProof.settled())
            await page.evaluate(() => window.__nativeCycleProof.retire())
            const state = await page.evaluate(() => ({ marker: window.__nativeCycleProof.marker,
                navigations: window.__nativeCycleProof.navigations, errors: window.FffAssetInjector.getDiagnostics().records }))
            assert.equal(state.marker, marker, 'Navigation replaced the document instead of using Livewire')
            assert.equal(state.navigations, (cycle + 1) * 2)
            assert.deepEqual(state.errors, [])
            if ((cycle + 1) % 10 === 0) console.log(`Native lifecycle: ${cycle + 1}/${cycles} modal and navigation round trips passed`)
        }
        const result = await page.evaluate(() => ({ mounted: window.__nativeCycleProof.mounted,
            retired: window.__nativeCycleProof.retired, navigations: window.__nativeCycleProof.navigations,
            remainingOwnedTimers: window.__nativeCycleProof.timers.size,
            remainingConsumers: window.FffAssetInjector.getConsumerGraph().getActiveConsumerIds().length,
            retainedResources: window.FffAssetInjector.getConsumerGraph().getRetainedResources().length,
            diagnostics: window.FffAssetInjector.getDiagnostics().records }))
        assert.equal(result.mounted, cycles * 2); assert.equal(result.retired, cycles * 2)
        return { passed: true, cycles, ...result,
            scope: 'Actual FlexTextInput factories, native Action cancellation and Livewire SPA body replacement; factory/binding disposal, component-owned status timers and graph resource claims. No claim about every capture capability, physical device, browser heap or evaluated JavaScript unloading.' }
    } finally {
        await page.evaluate(() => { if (window.__nativeCycleProof) window.clearTimeout = window.__nativeCycleProof.clearTimeout }).catch(() => {})
    }
}
