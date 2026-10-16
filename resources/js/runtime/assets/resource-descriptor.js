export function assertResourceKind(kind) {
    if (kind !== 'stylesheet' && kind !== 'chunk') throw Error('Invalid FLEX asset resource kind.')
}

/** Resource kind comes from the declared plan, never from a URL suffix. */
export function resourceKey({ kind, url }) {
    assertResourceKind(kind)
    if (typeof url !== 'string' || !url || url.length > 4096) {
        throw Error('Invalid FLEX asset resource descriptor.')
    }
    return `${kind}:${url}`
}

export function normalizeResources(resources, normalizeUrl = value => value) {
    if (!Array.isArray(resources) || resources.length > 1025) {
        throw Error('FLEX asset resources require a bounded list.')
    }
    const unique = new Map()
    for (const resource of resources) {
        if (!resource || Array.isArray(resource) || Object.keys(resource).length !== 2
            || !Object.hasOwn(resource, 'kind') || !Object.hasOwn(resource, 'url')) {
            throw Error('Invalid FLEX asset resource descriptor.')
        }
        resourceKey(resource)
        const descriptor = Object.freeze({ kind: resource.kind, url: normalizeUrl(resource.url) })
        unique.set(resourceKey(descriptor), descriptor)
    }
    return Object.freeze([...unique.values()])
}

export function resourcesFromPlan({ stylesheets = [], chunks = [], entry = null }, normalizeUrl) {
    return normalizeResources([
        ...stylesheets.map(url => ({ kind: 'stylesheet', url })),
        ...chunks.map(url => ({ kind: 'chunk', url })),
        ...(entry ? [{ kind: 'chunk', url: entry }] : []),
    ], normalizeUrl)
}
