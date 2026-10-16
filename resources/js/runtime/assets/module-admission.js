import { retainAssetAdmission } from './shared-admission.js'
import { assertAssetDelivery } from './delivery-policy.js'
import { createAssetLinkStore } from './asset-link-store.js'

/** Imported bytes keep browser semantics; only explicit admission/interest is owned. */
export function importAdmittedModule(url, { document = globalThis.document, signal = null, priority = 'high', bytes = null } = {},
    importer = url => import(/* @vite-ignore */ url)) {
    if (!document) return Promise.reject(Error('FLEX native import requires a document.'))
    let descriptor
    try { descriptor = assertAssetDelivery(document, url) } catch (error) { return Promise.reject(error) }
    const normalized = descriptor.url
    if (!normalized) return Promise.reject(Error('FLEX native import requires a valid URL and document.'))
    // import() has no per-call SRI option. Prepare the declared fetch settings
    // before evaluation; a warm module map cannot be retroactively attested.
    // Initial import-map integrity and application CSP cover that wider scope.
    if (Object.values(descriptor.attributes).some(value => value !== null)) {
        const store = createAssetLinkStore({ document, window: document.defaultView ?? globalThis.window })
        return store.load(normalized, 'chunk', { signal, priority }).then(() =>
            evaluate(normalized, { document, signal, priority, bytes }, importer),
        ).finally(() => store.dispose())
    }
    return evaluate(normalized, { document, signal, priority, bytes }, importer)
}

function evaluate(normalized, { document, signal, priority, bytes }, importer) {
    const admission = retainAssetAdmission(document)
    const key = `evaluation:${normalized}`
    const interest = admission.scheduler.schedule(key, () => importer(normalized), { signal, priority, bytes })
    // Keep the coordinator alive for the real native flight, even when this
    // owner's interest ends first. A replacement runtime must reuse its budget.
    const flight = admission.scheduler.inflight.get(key)
    if (flight) admission.trackFlight(flight)
    admission.release()
    return interest
}
