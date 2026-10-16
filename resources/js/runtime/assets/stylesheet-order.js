import { resolveAssetUrl, STYLESHEET_SELECTOR } from './asset-url.js'
import { getRuntimeState } from '../runtime-state.js'

const failure = message => Object.assign(Error(message), { code: 'asset_policy_failed' })

/** Only ranked FLEX links participate; native/application links are never moved. */
export function createStylesheetOrder(document) {
    const catalogs = getRuntimeState().stylesheetOrders ??= new WeakMap()
    const markers = new WeakMap()
    const read = () => {
        const declarations = document.querySelectorAll('[data-fff-style-order]')
        if (declarations.length > 2) throw failure('Too many FLEX stylesheet order declarations.')
        let catalog = catalogs.get(document)
        for (const node of declarations) {
            const raw = node.getAttribute('data-fff-style-order')
            if (typeof raw !== 'string' || raw.length * 2 > 524288) throw failure('FLEX stylesheet order exceeds its metadata budget.')
            const previous = markers.get(node)
            if (previous?.raw === raw && previous.baseURI === document.baseURI) {
                if (catalog && catalog.identity !== previous.catalog.identity) throw failure('FLEX stylesheet order changed; reload required.')
                catalog ??= previous.catalog
                continue
            }
            let value
            try { value = JSON.parse(raw) } catch { throw failure('Invalid FLEX stylesheet order JSON.') }
            if (!value || Array.isArray(value) || Object.keys(value).length !== 2 || value.format !== 1
                || !Array.isArray(value.stylesheets) || value.stylesheets.length > 2048) throw failure('Invalid FLEX stylesheet order.')
            const urls = value.stylesheets.map(href => {
                if (typeof href !== 'string' || href.length > 4096 || /[\\\s\u0000-\u001f\u007f<>"`#]/.test(href)
                    || !(/^https?:\/\//.test(href) || /^\/(?!\/)/.test(href))) throw failure('Invalid FLEX stylesheet order URL.')
                const url = resolveAssetUrl(href, document.baseURI)
                if (!url || new URL(url).username || new URL(url).password) throw failure('Invalid FLEX stylesheet order URL.')
                return url
            })
            if (new Set(urls).size !== urls.length) throw failure('Duplicate FLEX stylesheet cascade resource.')
            const identity = JSON.stringify(urls)
            if (catalog && catalog.identity !== identity) throw failure('FLEX stylesheet order changed; reload required.')
            catalog ??= { identity, ranks: new Map(urls.map((url, rank) => [url, rank])) }
            markers.set(node, { raw, baseURI: document.baseURI, catalog })
        }
        if (catalog) catalogs.set(document, catalog)
        return catalog
    }
    let catalog = read()
    // Sorting transport links must not scan thousands of fields/table cells.
    const ranked = () => [...document.head.querySelectorAll(STYLESHEET_SELECTOR)]
        .filter(link => link.parentNode === document.head && catalog?.ranks.has(resolveAssetUrl(link.href, document.baseURI)))
    const normalize = () => {
        catalog = read()
        if (!catalog) return
        const actual = ranked()
        const ordered = [...actual].sort((a, b) => catalog.ranks.get(resolveAssetUrl(a.href, document.baseURI)) - catalog.ranks.get(resolveAssetUrl(b.href, document.baseURI)))
        // Insert only displaced FLEX nodes. Already ordered SSR links are untouched.
        for (let index = 0; index < ordered.length; index++) {
            if (actual[index] === ordered[index]) continue
            const previous = actual.indexOf(ordered[index])
            document.head.insertBefore(ordered[index], actual[index])
            actual.splice(previous, 1); actual.splice(index, 0, ordered[index])
        }
    }
    normalize()
    return {
        normalize,
        insert(link) {
            const rank = catalog?.ranks.get(resolveAssetUrl(link.href, document.baseURI))
            if (rank == null) { document.head.appendChild(link); return }
            const links = ranked()
            const next = links.find(candidate => catalog.ranks.get(resolveAssetUrl(candidate.href, document.baseURI)) > rank)
            const last = links.at(-1)
            document.head.insertBefore(link, next ?? last?.nextSibling ?? null)
        },
    }
}
