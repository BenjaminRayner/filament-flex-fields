/** Shared template transport, scoped to document identity and present payloads. */
export function createDocumentRegistry({selector, merge}) {
    let documents = new WeakMap()
    const read = (root = typeof document === 'undefined' ? null : document) => {
        if (!root) return merge([])
        let cache = documents.get(root)
        if (!cache) { cache = {nodes: new WeakMap(), signature: [], merged: null}; documents.set(root, cache) }
        const signature = Array.from(root.querySelectorAll(selector), element => ({element,
            text: (element.tagName === 'TEMPLATE' ? element.content?.textContent : element.textContent)?.trim() ?? '',
        }))
        if (cache.merged && signature.length === cache.signature.length
            && signature.every((item, i) => item.element === cache.signature[i].element && item.text === cache.signature[i].text)) return cache.merged
        const payloads = []
        for (const {element, text} of signature) {
            let parsed = cache.nodes.get(element)
            if (!parsed || parsed.text !== text) {
                let payload = null
                try { if (text && text.length <= 1048576) payload = JSON.parse(text) } catch { /* Pending morph data is retryable. */ }
                parsed = {text, payload}; cache.nodes.set(element, parsed)
            }
            if (parsed.payload) payloads.push(parsed.payload)
        }
        cache.signature = signature
        return cache.merged = merge(payloads)
    }
    const reset = () => { documents = new WeakMap() }
    if (typeof document !== 'undefined') document.addEventListener('livewire:navigated', reset)
    return {
        read, reset,
        async ensure() {
            const initial = read()
            if (Object.values(initial.pools).some(pool => Object.keys(pool).length)) return initial
            if (typeof document === 'undefined') return initial
            await new Promise(resolve => {
                let settled = false, frame = 0, deadline = 0
                const finish = () => {
                    if (settled) return
                    settled = true; cancelAnimationFrame(frame); clearTimeout(deadline); resolve()
                }
                frame = requestAnimationFrame(finish)
                deadline = setTimeout(finish, 100)
            })
            return read()
        },
    }
}
