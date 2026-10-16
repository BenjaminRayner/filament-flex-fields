/** Public client handles own subscriptions; the engine owns shared link transport. */
export function createAssetStoreClients({ transport, subscriptions, onLastRelease, limit = 4096 }) {
    const clients = new Set()
    const acquire = (options = {}) => {
        if (transport.disposed) throw Error('FLEX asset store is disposed.')
        if (clients.size >= limit) throw Error('FLEX asset store client budget exhausted.')
        const registrations = new Set()
        const defaultFlights = new Map()
        const controller = new AbortController()
        let disposed = false
        const configure = (settings = {}) => {
            if (disposed) throw Error('FLEX asset store client is disposed.')
            if (registrations.size >= limit) throw Error('FLEX client subscription budget exhausted.')
            const unsubscribe = subscriptions.subscribe(settings)
            if (!Object.values(settings).some(value => value != null)) return unsubscribe
            const release = () => { registrations.delete(release); unsubscribe() }
            registrations.add(release)
            return release
        }
        configure(options)
        const client = {
            get disposed() { return disposed || transport.disposed },
            configure,
            load(href, kind, settings = {}) {
                if (client.disposed) return Promise.reject(Error('FLEX asset store client is disposed.'))
                if (settings.signal) return transport.load(href, kind,
                    { ...settings, signal: AbortSignal.any([controller.signal, settings.signal]) })
                const key = JSON.stringify([kind, href])
                const existing = defaultFlights.get(key)
                if (existing) {
                    // Promotion changes admission, not this client's interest identity.
                    if (existing.priority === 'low' && settings.priority !== 'low') {
                        existing.priority = settings.priority ?? 'auto'
                        transport.load(href, kind, { ...settings, signal: controller.signal }).catch(() => {})
                    }
                    return existing.promise
                }
                if (defaultFlights.size >= limit) return Promise.reject(Error('FLEX client load interest budget exhausted.'))
                const promise = transport.load(href, kind, { ...settings, signal: controller.signal })
                const entry = { promise, priority: settings.priority ?? 'auto' }
                defaultFlights.set(key, entry)
                const release = () => { if (defaultFlights.get(key) === entry) defaultFlights.delete(key) }
                promise.then(release, release)
                return promise
            },
            isLoaded: (...args) => !client.disposed && transport.isLoaded(...args),
            connected: transport.connected,
            inflight: transport.inflight,
            schedulerSnapshot: transport.schedulerSnapshot,
            snapshot: transport.snapshot,
            clientSnapshot: () => ({ clients: clients.size, subscriptions: subscriptions.snapshot() }),
            dispose() {
                if (disposed) return
                disposed = true
                defaultFlights.clear()
                controller.abort()
                for (const release of [...registrations]) release()
                clients.delete(client)
                if (!clients.size) onLastRelease()
            },
        }
        client.forget = (href, kind) => {
            if (client.disposed) return
            defaultFlights.delete(JSON.stringify([kind, href]))
            return transport.forget(href, kind)
        }
        for (const name of ['registerSizes', 'resync', 'discover', 'dedupe']) {
            client[name] = (...args) => { if (!client.disposed) return transport[name](...args) }
        }
        clients.add(client)
        return client
    }
    return { acquire }
}
