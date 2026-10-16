const registryKey = Symbol.for('filament-flex-fields.location-assets.v4')

/** One flight per document/resource identity. SDK and CSS must both settle before construction. */
export async function loadMapRuntime(provider, assets, { document: doc = document, window: win = window, timeout = 20000 } = {}) {
    const registry = doc[registryKey] ?? (doc[registryKey] = new Map())
    const identity = JSON.stringify([provider, assets.script, assets.stylesheet, assets.module])
    if (registry.has(identity)) {
        // Consumer retirement can remove SDK CSS while evaluated JS stays cached.
        // A returning field must restore CSS before constructing its next map.
        const stylesheet = new URL(assets.stylesheet, doc.baseURI).href
        return registry.get(identity).then(sdk => {
            if ([...doc.querySelectorAll('link[rel="stylesheet"][href]')].some(node => node.href === stylesheet && node.sheet)) return sdk
            registry.delete(identity)
            return loadMapRuntime(provider, assets, { document: doc, window: win, timeout })
        })
    }
    const owned = []; const cancels = []
    const namespace = () => provider === 'mapbox' ? win.mapboxgl : win.maplibregl
    const loadNode = (tag, url) => new Promise((resolve, reject) => {
        const attr = tag === 'script' ? 'src' : 'href'
        let node = [...doc.querySelectorAll(tag === 'script' ? 'script[src]' : 'link[rel="stylesheet"][href]')].find(node => node[attr] === new URL(url, doc.baseURI).href)
        if (node && (tag === 'script' ? namespace()?.Map : node.sheet)) { resolve(); return }
        const created = !node
        if (created) {
            node = doc.createElement(tag); node[attr] = url
            if (tag === 'script') node.async = true
            else node.rel = 'stylesheet'
            const nonce = doc.querySelector('script[nonce]')?.nonce
            if (nonce) node.nonce = nonce
            owned.push(node)
        }
        let settled = false
        const finish = error => {
            if (settled) return; settled = true
            clearTimeout(timer); node.removeEventListener('load', onLoad); node.removeEventListener('error', onError)
            error ? reject(error) : resolve()
        }
        const onLoad = () => finish(tag === 'script' && !namespace()?.Map ? new Error('Map SDK has no constructor.') : null)
        const onError = () => finish(new Error('Map resource failed to load.'))
        const timer = setTimeout(() => finish(new Error('Map resource timed out.')), timeout)
        node.addEventListener('load', onLoad, { once: true }); node.addEventListener('error', onError, { once: true })
        cancels.push(() => finish(new Error('Map resource cancelled.')))
        if (created) doc.head.appendChild(node)
    })
    const sdk = assets.module ? import(/* @vite-ignore */ assets.script) : namespace()?.Map ? Promise.resolve(namespace()) : loadNode('script', assets.script).then(namespace)
    // Module import is not cancellable, but callers and the registry never publish a timed-out result.
    let timer
    const flight = Promise.race([Promise.all([sdk, loadNode('link', assets.stylesheet)]), new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Map SDK timed out.')), timeout) })])
        .then(([module]) => { if (!module?.Map) throw new Error('Invalid map SDK.'); return module })
        .catch(error => { cancels.forEach(cancel => cancel()); owned.forEach(node => node.remove()); registry.delete(identity); throw error })
        .finally(() => clearTimeout(timer))
    registry.set(identity, flight)
    return flight
}
