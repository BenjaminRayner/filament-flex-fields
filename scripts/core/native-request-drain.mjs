/** Track current requests across Livewire navigation, whose original load state stays reached. */
export function trackNativeRequests(page, { timeout = 20000 } = {}) {
    const pending = new Set(), waiters = new Set()
    const started = request => pending.add(request)
    const settled = request => {
        pending.delete(request)
        for (const check of [...waiters]) check()
    }
    page.on('request', started)
    page.on('requestfinished', settled)
    page.on('requestfailed', settled)
    return {
        async drain() {
            await page.waitForLoadState('load')
            if (!pending.size) return
            await new Promise((resolve, reject) => {
                let timer
                const cleanup = () => { clearTimeout(timer); waiters.delete(check) }
                const check = () => { if (!pending.size) { cleanup(); resolve() } }
                waiters.add(check)
                timer = setTimeout(() => {
                    cleanup()
                    reject(new Error('Native fixture requests did not settle: ' + [...pending].map(request => request.url()).join(', ')))
                }, timeout)
                check()
            })
        },
        dispose() {
            page.off('request', started)
            page.off('requestfinished', settled)
            page.off('requestfailed', settled)
        },
    }
}
