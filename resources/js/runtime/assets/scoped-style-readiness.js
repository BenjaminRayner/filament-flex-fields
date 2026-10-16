const GUARD_GROUP = '(--fff-asset-paint-guard:1)'
const FALLBACK_GROUP = '(--fff-asset-paint-fallback:1)'
const condition = rule => rule.conditionText?.replace(/\s/g, '')

/** Progressive CSS-only scoping. Consumer readiness stays entirely data-owned;
 * no observer, flag or async task is responsible for sealing newly inserted HTML.
 */
export function enableScopedStyleReadiness({ document, window }) {
    if (typeof window.CSSScopeRule !== 'function') return () => {}
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
        document.removeEventListener?.('load', loaded, true)
        for (const [group, entries] of retired) restore(group, entries)
        retired.clear()
    }
}
