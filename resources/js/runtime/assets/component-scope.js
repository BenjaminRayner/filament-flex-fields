/** Resolve the nearest ownership marker, rather than the union of all instances of a type. */
export function componentAssetScope(element, componentId = null) {
    let current = element
    while (current && current.nodeType === 1) {
        // A page-level queued marker cannot describe one isolated field instance.
        if (current.tagName === 'BODY' || current.tagName === 'HTML') break
        if (current.matches?.('[data-fff-asset-batch]') && (!componentId || current.dataset?.fffAssetConsumer === componentId)) return current
        for (const child of current.children ?? []) {
            if (child.matches?.('[data-fff-asset-batch]') && (!componentId || child.dataset?.fffAssetConsumer === componentId)) return current
        }
        current = current.parentElement
    }
    return null
}

/** A nested child's marker is never its parent's instance plan. */
export function directAssetBatches(scope, componentId = null) {
    const children = scope?.matches?.('[data-fff-asset-batch]') ? [scope] : [...(scope?.children ?? [])]
    return children.filter(child => child.matches?.('[data-fff-asset-batch]')
        && (!componentId || child.dataset?.fffAssetConsumer === componentId))
}

/** Module filenames and nested sibling plans cannot identify an instance. */
export function declaredComponentIdentity(element) {
    const owner = componentAssetScope(element)
    const identities = new Set(directAssetBatches(owner).map(batch => batch.dataset?.fffAssetConsumer).filter(Boolean))
    const explicit = element?.dataset?.fffAssetConsumer
    if (explicit) identities.add(explicit)
    if (identities.size > 1) throw new Error('Conflicting FLEX component identities describe the same instance boundary.')
    if (identities.size === 1) return identities.values().next().value
    return element?.closest?.('[data-fff-asset-consumer]')?.dataset?.fffAssetConsumer ?? null
}
