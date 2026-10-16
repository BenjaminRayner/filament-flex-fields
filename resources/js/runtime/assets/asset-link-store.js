import { resolveAssetUrl, STYLESHEET_SELECTOR, CHUNK_SELECTOR } from './asset-url.js'
import { retainAssetAdmission } from './shared-admission.js'
import { getRuntimeState } from '../runtime-state.js'
import { assertResourceKind } from './resource-descriptor.js'
import { createOwnedSubscriptions } from '../lifecycle/owned-subscriptions.js'
import { createAssetStoreClients } from './asset-store-clients.js'
import { retainRuntimeDiagnostics } from '../diagnostics/document-diagnostics.js'
import { createStylesheetOrder } from './stylesheet-order.js'
import { createIntegrityModuleTransport } from './integrity-module-transport.js'

import { assertAssetDelivery, applyDeliveryAttributes, assertLinkDelivery } from './delivery-policy.js'

const LOAD_TIMEOUT_MS = 15_000

/**
 * Owns link transport, readiness and retries. DOM discovery and component
 * ownership deliberately remain outside this store.
 */
export function createAssetLinkStore({ document, window, onLoad, onReady, isProtected }) {
    const state = getRuntimeState()
    state.assetStores ??= new WeakMap()
    const existing = state.assetStores.get(document)
    const options = { onLoad, onReady, isProtected }
    if (existing && !existing.disposed) return existing.retain(options)

    // Admit immutable metadata before acquiring transport/listener resources.
    const stylesheetOrder = createStylesheetOrder(document)

    const diagnostics = retainRuntimeDiagnostics({ document, window })
    const subscriptions = createOwnedSubscriptions({ names: ['onLoad', 'onReady', 'isProtected'],
        reportFailure: (error, channel, context) => diagnostics.report(error, {
            ...context, code: channel === 'isProtected' ? 'callback_contract_failed' : 'callback_failed', phase: 'callback',
        }) })
    let disposed = false
    const notify = (channel, url, kind, componentId = null) => subscriptions.notify(channel, [url, kind], { kind, componentId })
    const protectedLink = link => subscriptions.protectedBy('isProtected', [link], {
        kind: link?.rel === 'stylesheet' ? 'stylesheet' : 'chunk', componentId: link?.dataset?.fffComponent,
    })
    const records = new Map()
    const byteSizes = new Map()
    const admission = retainAssetAdmission(document)
    const scheduler = admission.scheduler
    const lifetime = new AbortController()
    const inflight = new Map()
    // Verified module scripts and successful preloads populate the native module map.
    state.preparedDeliveries ??= new WeakMap()
    let preparedDelivery = state.preparedDeliveries.get(document)
    if (!preparedDelivery) { preparedDelivery = new Map(); state.preparedDeliveries.set(document, preparedDelivery) }
    const deliveryIdentity = attributes => JSON.stringify(attributes)
    const rememberDelivery = (url, attributes) => {
        if (!attributes.integrity) return
        if (!preparedDelivery.has(url) && preparedDelivery.size >= 2048) throw Object.assign(Error('FLEX verified module metadata budget exhausted.'), { code: 'asset_policy_failed' })
        preparedDelivery.set(url, deliveryIdentity(attributes))
    }
    const preparedModules = getRuntimeState().preparedModules
    const indexes = { stylesheet: new Map(), chunk: new Map() }
    const indexed = new Set()
    const selectorFor = (kind) => kind === 'stylesheet' ? STYLESHEET_SELECTOR : CHUNK_SELECTOR
    const keyFor = (kind, url) => { assertResourceKind(kind); return `${kind}:${url}` }
    const connected = (link) => Boolean(link && link.isConnected !== false && link.parentNode)
    const urlFor = (url) => resolveAssetUrl(url, document.baseURI)
    const ready = (link, kind) => kind === 'stylesheet'
        ? Boolean(link?.sheet)
        : link?.dataset?.fffAssetState === 'ready'

    const indexDocument = (kind) => {
        const index = indexes[kind]
        index.clear()
        indexed.add(kind)

        for (const link of document.querySelectorAll(selectorFor(kind))) {
            const url = urlFor(link.href)

            if (url && connected(link) && !index.has(url)) {
                index.set(url, link)
            }
        }
    }

    const find = (kind, url) => {
        const cached = indexes[kind].get(url)

        if (connected(cached)) {
            return cached
        }

        if (!indexed.has(kind)) indexDocument(kind)
        const found = indexes[kind].get(url)
        if (connected(found)) return found
        indexes[kind].delete(url)
        return null
    }

    const isLoaded = (href, kind) => {
        assertResourceKind(kind)
        try {
            const { url, attributes } = assertAssetDelivery(document, href)
            if (kind === 'chunk' && preparedModules.has(url)) {
                return !attributes.integrity || preparedDelivery.get(url) === deliveryIdentity(attributes)
            }
            const record = records.get(keyFor(kind, url))
            const link = connected(record?.link) ? record.link : find(kind, url)
            if (!connected(link)) return false
            assertLinkDelivery(link, attributes)
            return ready(link, kind)
        } catch { return false }
    }

    const forget = (href, kind) => {
        const url = urlFor(href)
        const key = keyFor(kind, url)
        const record = records.get(key)

        scheduler.cancel(key)
        record?.cancel?.()
        records.delete(key)
        indexes[kind].delete(url)
    }

    const performLoad = (href, kind, { componentId = null, priority = 'auto', timeoutMs = LOAD_TIMEOUT_MS } = {}) => {
        if (disposed) return Promise.reject(new Error('Flex Fields asset store is disposed.'))
        const { url, attributes } = assertAssetDelivery(document, href)

        if (!url) {
            return Promise.reject(new Error('Invalid Flex Fields asset URL.'))
        }

        if (kind === 'chunk' && preparedModules.has(url)) {
            if (attributes.integrity && preparedDelivery.get(url) !== deliveryIdentity(attributes)) {
                return Promise.reject(Object.assign(Error('An already prepared FLEX module has no matching integrity verification; reload required.'), { code: 'asset_policy_failed' }))
            }
            return Promise.resolve()
        }
        if (kind === 'chunk' && attributes.integrity && !preparedDelivery.has(url) && preparedDelivery.size >= 2048) return Promise.reject(Object.assign(Error('FLEX verified module metadata budget exhausted.'), { code: 'asset_policy_failed' }))
        const key = keyFor(kind, url)
        const current = records.get(key)

        if (current?.state === 'loading') {
            if (priority === 'high' && current.link) {
                current.link.fetchPriority = 'high'
            }

            return current.promise
        }

        if (kind === 'chunk' && attributes.integrity) {
            const transport = createIntegrityModuleTransport({ document, window, url, attributes, timeoutMs })
            const record = { state: 'loading', link: null, promise: null, cancel: transport.cancel }
            record.promise = transport.promise.then(() => {
                rememberDelivery(url, attributes)
                preparedModules.add(url)
                record.state = 'ready'; record.cancel = null
                if (records.get(key) === record) notify('onReady', url, kind, componentId)
            }).catch(error => {
                if (records.get(key) === record) records.delete(key)
                diagnostics.report(error, { code: error.code ?? 'asset_load_failed', phase: 'transport', kind, componentId })
                throw error
            })
            records.set(key, record)
            notify('onLoad', url, kind, componentId)
            return record.promise
        }

        let link = find(kind, url)
        if (link) assertLinkDelivery(link, attributes)

        if (link && ready(link, kind)) {
            link.dataset.fffManagedAsset = 'true'
            if (kind === 'chunk') { rememberDelivery(url, attributes); preparedModules.add(url) }
            const promise = Promise.resolve()
            records.set(key, { state: 'ready', link, promise, cancel: null })
            notify('onReady', url, kind, componentId)
            return promise
        }

        // A preload emitted before this store booted may have already fired its
        // event. Recreate only that unmanaged, unobserved hint; browser cache is reused.
        if (link && (link.dataset?.fffAssetState === 'failed'
            || (kind === 'chunk' && !records.has(key) && !ready(link, kind) && link.dataset?.fffManagedAsset !== 'true'))) {
            link.remove()
            indexes[kind].delete(url)
            link = null
        }

        const created = !link
        link ??= document.createElement('link')
        applyDeliveryAttributes(link, attributes)
        const relation = kind === 'stylesheet' ? 'stylesheet' : 'modulepreload'
        // Adopt in-flight SSR links without restarting their browser request.
        if (link.rel !== relation) link.rel = relation
        if (link.href !== url) link.href = url
        link.dataset.fffManagedAsset = 'true'
        link.dataset.fffAssetState = 'loading'
        link.dataset[kind === 'stylesheet' ? 'fffInjectedStylesheet' : 'fffInjectedChunk'] = 'true'

        if (componentId) {
            link.dataset.fffComponent = componentId
        }

        link.fetchPriority = priority

        const record = { state: 'loading', link, promise: null, cancel: null }
        records.set(key, record)
        indexes[kind].set(url, link)

        record.promise = new Promise((resolve, reject) => {
            let settled = false
            let timer = null

            const finish = (error = null) => {
                if (settled) {
                    return
                }

                if (!error && kind === 'chunk') {
                    try { rememberDelivery(url, attributes) } catch (failure) { error = failure }
                }
                settled = true
                window.clearTimeout(timer)
                link.removeEventListener('load', handleLoad)
                link.removeEventListener('error', handleError)
                record.cancel = null
                record.state = error ? 'failed' : 'ready'
                link.dataset.fffAssetState = record.state

                if (error) {
                    if (!protectedLink(link)) {
                        link.remove()
                    }

                    indexes[kind].delete(url)
                    records.delete(key)
                    diagnostics.report(error, { code: error.code ?? 'asset_load_failed', phase: 'transport', kind, componentId })
                    reject(error)
                } else {
                    if (kind === 'chunk') preparedModules.add(url)
                    notify('onReady', url, kind, componentId)
                    resolve()
                }
            }
            const handleLoad = () => finish()
            const handleError = () => finish(new Error(`Flex Fields ${kind} failed to load.`))

            record.cancel = () => finish(new DOMException('Flex Fields asset load cancelled.', 'AbortError'))
            link.addEventListener('load', handleLoad, { once: true })
            link.addEventListener('error', handleError, { once: true })
            timer = window.setTimeout(() => finish(Object.assign(new Error(`Flex Fields ${kind} load timed out.`), { code: 'asset_timeout' })), timeoutMs)

            if (created) {
                notify('onLoad', url, kind, componentId)
                try {
                    if (kind === 'stylesheet') stylesheetOrder.insert(link)
                    else document.head.appendChild(link)
                } catch (error) { finish(error) }
            } else if (ready(link, kind)) {
                finish()
            }
        })

        return record.promise
    }

    const load = (href, kind, options = {}) => {
        assertResourceKind(kind)
        if (disposed) return Promise.reject(new Error('Flex Fields asset store is disposed.'))
        if (options.signal?.aborted) return Promise.reject(new DOMException('FLEX asset consumer detached.', 'AbortError'))
        let url
        try { url = assertAssetDelivery(document, href).url }
        catch (error) {
            diagnostics.report(error, { code: 'asset_policy_failed', phase: 'preparation', kind, componentId: options.componentId })
            return Promise.reject(error)
        }
        if (!url) return Promise.reject(new Error('Invalid Flex Fields asset URL.'))
        if (isLoaded(url, kind)) return Promise.resolve().then(() => {
            options.signal?.throwIfAborted()
            return performLoad(url, kind, options)
        }).then(value => { options.signal?.throwIfAborted(); return value })
        const settings = { ...options }
        const key = keyFor(kind, url)
        const interest = scheduler.schedule(key, () => performLoad(url, kind, settings), {
            signal: settings.signal ? AbortSignal.any([lifetime.signal, settings.signal]) : null,
            priority: settings.priority ?? 'auto',
            bytes: byteSizes.get(url) ?? null,
            onPromote(priority) {
                settings.priority = priority
                const record = records.get(keyFor(kind, url))
                if (record?.link) record.link.fetchPriority = priority
            },
        })
        const flight = scheduler.inflight.get(key)
        if (flight && inflight.get(key) !== flight) {
            inflight.set(key, flight)
            const finished = () => { if (inflight.get(key) === flight) inflight.delete(key) }
            flight.then(finished, finished)
        }
        return interest
    }

    const discover = (root = document) => {
        for (const kind of ['stylesheet', 'chunk']) {
            const selector = selectorFor(kind)
            const links = [
                ...(root.matches?.(selector) ? [root] : []),
                ...(root.querySelectorAll?.(selector) ?? []),
            ]
            for (const link of links) {
                const url = urlFor(link.href)
                if (url && connected(link) && !connected(indexes[kind].get(url))) indexes[kind].set(url, link)
            }
            if (root === document) indexed.add(kind)
        }
    }

    const resync = () => {
        stylesheetOrder.normalize()
        for (const kind of ['stylesheet', 'chunk']) {
            indexDocument(kind)
        }

        for (const [key, record] of records) {
            if (record.link && !connected(record.link)) {
                record.cancel?.()
                records.delete(key)
            }
        }
    }

    const dedupe = () => {
        for (const kind of ['stylesheet', 'chunk']) {
            const seen = new Map()

            for (const link of document.querySelectorAll(selectorFor(kind))) {
                const url = urlFor(link.href)

                if (!url || !connected(link)) {
                    continue
                }

                const record = records.get(keyFor(kind, url))
                const preferred = connected(record?.link) ? record.link : seen.get(url)

                if (preferred && preferred !== link && !protectedLink(link)) {
                    link.remove()
                    continue
                }

                seen.set(url, link)

                if (link.parentNode !== document.head) {
                    if (kind === 'stylesheet') stylesheetOrder.insert(link)
                    else document.head.appendChild(link)
                }
            }

            indexes[kind] = seen
        }
        stylesheetOrder.normalize()
    }

    const api = {
        retain: settings => clients.acquire(settings),
        registerSizes(sizes) {
            if (!sizes || typeof sizes !== 'object' || Array.isArray(sizes)) return
            for (const [href, bytes] of Object.entries(sizes)) {
                const url = urlFor(href)
                if (url && Number.isSafeInteger(bytes) && bytes >= 0) {
                    byteSizes.set(url, bytes)
                    while (byteSizes.size > 1024) byteSizes.delete(byteSizes.keys().next().value)
                }
            }
        },
        get disposed() { return disposed },
        load,
        isLoaded,
        forget,
        resync,
        discover,
        dedupe,
        inflight,
        connected,
        schedulerSnapshot: scheduler.snapshot,
        snapshot: () => [...records.values()].map(({ state, link }) => ({
            state,
            kind: link?.rel === 'stylesheet' ? 'stylesheet' : 'chunk',
        })),
        dispose() {
            if (disposed) return
            disposed = true
            if (state.assetStores.get(document) === api) state.assetStores.delete(document)
            lifetime.abort()
            for (const key of [...inflight.keys()]) {
                if (key.startsWith('stylesheet:') || key.startsWith('chunk:')) {
                    scheduler.cancel(key, new Error('Flex Fields asset store is disposed.'))
                }
            }
            for (const record of [...records.values()]) {
                record.cancel?.()
            }

            admission.release()
            subscriptions.dispose()
            diagnostics.release()
            records.clear()
            byteSizes.clear()
            indexed.clear()
            indexes.stylesheet.clear()
            indexes.chunk.clear()
        },
    }
    const clients = createAssetStoreClients({ transport: api, subscriptions, onLastRelease: api.dispose })
    state.assetStores.set(document, api)
    try { return clients.acquire(options) } catch (error) { api.dispose(); throw error }
}
