const GUARD_GROUP = '(--fff-asset-paint-guard:1)'
const FALLBACK_GROUP = '(--fff-asset-paint-fallback:1)'
const condition = rule => rule.conditionText?.replace(/\s/g, '')

/** Progressive root annotation. Readiness remains per marker and CSS owns the
 * opacity seal; unsupported/CSP-blocked annotation retains the full fallback.
 */
function createScopedStyleReadiness({ document, window }) {
    if (typeof window.CSSScopeRule !== 'function') return () => {}
    const Observer = window.MutationObserver
    if (typeof Observer !== 'function') return () => {}
    // Presentation identity only: CSS readiness still belongs to each marker.
    // The generic parent opacity seal stays installed even before annotation.
    const mark = batch => {
        const owner = batch.parentElement
        if (owner && !['HTML', 'BODY'].includes(owner.tagName) && !owner.hasAttribute('data-fff-paint-owner')) {
            owner.setAttribute('data-fff-paint-owner', '')
        }
    }
    const annotate = root => {
        if (root.matches?.('[data-fff-asset-batch]')) mark(root)
        for (const batch of root.querySelectorAll?.('[data-fff-asset-batch]') ?? []) mark(batch)
    }
    const observer = new Observer(mutations => {
        const roots = new Set()
        for (const mutation of mutations) for (const node of mutation.addedNodes) {
            if (node.nodeType === 1) roots.add(node)
        }
        for (const root of roots) {
            let ancestor = root.parentElement
            while (ancestor && !roots.has(ancestor)) ancestor = ancestor.parentElement
            if (!ancestor) annotate(root)
        }
    })
    observer.observe(document, { subtree: true, childList: true })
    annotate(document)
    const inspected = new WeakSet()
    const retired = new Map()
    let disposed = false

    const restore = (group, entries) => {
        for (const { index, text } of [...entries].sort((a, b) => a.index - b.index)) {
            try { group.insertRule(text, Math.min(index, group.cssRules.length)) }
            catch { /* Native scopes remain installed if a sheet becomes read-only. */ }
        }
    }
    const inspect = sheet => {
        if (disposed || !sheet || inspected.has(sheet)) return
        let rules
        try { rules = [...sheet.cssRules] }
        catch { return } // Unreadable/CDN sheets keep their original fallback.
        if (!rules.length) return
        inspected.add(sheet)
        for (const rule of rules) {
            if (condition(rule) !== GUARD_GROUP) continue
            const children = [...rule.cssRules]
            const scopes = children.filter(child => child instanceof window.CSSScopeRule)
            const pending = scopes.find(scope => scope.start?.includes('[data-fff-asset-batch]'))
            const settling = scopes.find(scope => scope.start?.includes('[data-fff-assets-settling]'))
            if ((pending?.cssRules?.length ?? 0) < 2 || !settling?.cssRules?.length) continue
            const fallback = children.find(child => condition(child) === FALLBACK_GROUP)
            if (!fallback || !fallback.cssRules.length) continue
            const removed = []
            try {
                for (let index = fallback.cssRules.length - 1; index >= 0; index--) {
                    const text = fallback.cssRules[index].cssText
                    fallback.deleteRule(index)
                    removed.push({ index, text })
                }
                retired.set(fallback, removed)
            } catch {
                restore(fallback, removed)
            }
        }
    }
    const loaded = event => {
        if (event.target?.tagName === 'LINK') inspect(event.target.sheet)
    }
    document.addEventListener?.('load', loaded, true)
    for (const sheet of document.styleSheets ?? []) inspect(sheet)
    return () => {
        if (disposed) return
        disposed = true
        observer.disconnect()
        document.removeEventListener?.('load', loaded, true)
        for (const [group, entries] of retired) restore(group, entries)
        retired.clear()
    }
}

// The blocking stylesheet helper lends its inspection to the first runtime
// retainer. No field readiness or factory is installed by this early lease.
const documents = new WeakMap()

export function primeScopedStyleReadiness({ document, window }) {
    if (documents.has(document)) return
    documents.set(document, { users: 0, dispose: createScopedStyleReadiness({ document, window }) })
}

export function enableScopedStyleReadiness({ document, window }) {
    primeScopedStyleReadiness({ document, window })
    const context = documents.get(document)
    context.users++
    let retained = true
    return () => {
        if (!retained) return
        retained = false
        if (--context.users) return
        context.dispose()
        documents.delete(document)
    }
}
