import { createStartupIntentBridge } from './intent-bridge.js'
import { registerStartupDirective } from './early-directive.js'

/** One module flight, bounded wait and per-DOM cancellation without eval/fetch. */
export function installStartupGate({ document, moduleUrl, importModule = url => import(url), deadlineMs = 15000 } = {}) {
    const key = Symbol.for('fff.core.startup.v1')
    const view = document?.defaultView
    if (!view || !moduleUrl) throw new Error('FLEX startup requires a document and its published ESM runtime URL.')
    const url = new URL(moduleUrl, document.baseURI).href
    const existing = view[key]
    if (existing) {
        if (existing.url !== url) throw new Error('Conflicting FLEX runtime releases in the same document.')
        return existing
    }
    const intents = createStartupIntentBridge(document)
    let flight = null
    const startup = { url, intents, start() {
        if (flight) return flight
        let timer
        const native = Promise.resolve().then(() => importModule(url))
        const deadline = new Promise((_, reject) => {
            timer = view.setTimeout(() => reject(new Error('FLEX startup module did not become ready.')), deadlineMs)
        })
        const current = Promise.race([native, deadline]).then(module => {
            if (typeof module.installCoreRuntime !== 'function' || typeof module.initializeEarlyOwner !== 'function') {
                throw new Error('FLEX runtime is missing its startup ABI.')
            }
            module.installCoreRuntime({ startup })
            return module
        }).catch(error => {
            if (flight === current) flight = null
            intents.cancel()
            throw error
        }).finally(() => view.clearTimeout(timer))
        flight = current
        return current
    } }
    view[key] = startup
    const register = () => registerStartupDirective(view.Alpine, startup)
    document.addEventListener('alpine:init', register, { once: true })
    register()
    return startup
}
