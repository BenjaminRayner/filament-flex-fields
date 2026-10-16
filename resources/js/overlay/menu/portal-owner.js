/** A portal belongs to its anchor's nearest focus trap, never a sibling/global modal. */
export function resolveMenuPortalParent(anchor) {
    let node = anchor
    let preservedOwner = null
    while (node) {
        if (node.tagName === 'DIALOG' && node.open) return node
        if (!preservedOwner && node.hasAttribute?.('wire:ignore')) preservedOwner = node
        if ([...(node.attributes ?? [])].some(attribute => attribute.name.startsWith('x-trap'))) {
            // Extra children of a native modal are deleted on its next morph.
            // A field's existing ignored DOM preserves the Alpine teleport node.
            return preservedOwner ?? node
        }
        node = node.parentElement
    }
    return anchor?.ownerDocument?.body ?? null
}

/** Keep Alpine teleport data/cleanup intact while moving its DOM into the focus boundary. */
export function mountOwnedMenuPortal(anchor, panel) {
    const parent = resolveMenuPortalParent(anchor)
    if (!parent || !panel || panel.parentElement === parent) return
    const move = () => parent.append(panel)
    const alpine = anchor.ownerDocument?.defaultView?.Alpine
    if (typeof alpine?.mutateDom === 'function') alpine.mutateDom(move)
    else move()
}
