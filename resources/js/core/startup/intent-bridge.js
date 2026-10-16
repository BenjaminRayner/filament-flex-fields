/** Short-lived pre-module buffer. Full activation/replay policy lives in ESM. */
export function createStartupIntentBridge(document) {
    const pending = new Map()
    let accepting = true
    const clear = owner => {
        const records = pending.get(owner)
        for (const record of records ?? []) if (record.type === 'click') record.node.removeAttribute('aria-busy')
        pending.delete(owner)
    }
    const cancel = () => { for (const owner of pending.keys()) clear(owner) }
    const capture = event => {
        if (!accepting) return
        if (event.key === 'Escape') { cancel(); return }
        const selector = event.type === 'click' ? '[data-fff-open-trigger]' : '[data-fff-edit-intent]'
        const node = event.target?.closest?.(selector), owner = node?.closest?.('[x-fff-load]')
        if (!owner || owner._fffFactoryReady === true || node.disabled || node.getAttribute('contenteditable') === 'false'
            || !node.getClientRects().length) return
        if (event.type === 'keydown' && (event.key === 'Tab' || ((event.ctrlKey || event.metaKey)
            && ['c', 'v'].includes(event.key.toLowerCase())))) return
        event.preventDefault(); event.stopImmediatePropagation()
        if (!pending.has(owner) && pending.size >= 1024) return
        const records = pending.get(owner) ?? []
        pending.set(owner, records)
        if (records.length >= 128) return
        const record = { node, type: event.type }
        if (event.type === 'click') node.setAttribute('aria-busy', 'true')
        else if (event.type === 'keydown') record.values = { key: event.key, code: event.code,
            altKey: event.altKey, ctrlKey: event.ctrlKey, metaKey: event.metaKey, shiftKey: event.shiftKey }
        else if (event.type === 'beforeinput') record.values = { data: event.data?.slice(0, 256) || null, inputType: event.inputType }
        else {
            const text = event.clipboardData?.getData('text/plain') || ''
            if (text.length > 256) return
            record.text = text
        }
        records.push(record)
    }
    const outside = event => { for (const owner of pending.keys()) if (!owner.contains(event.target)) clear(owner) }
    const disposed = event => clear(event.target)
    for (const type of ['click', 'keydown', 'beforeinput', 'paste']) document.addEventListener(type, capture, true)
    document.addEventListener('pointerdown', outside, true)
    document.addEventListener('fff-load-dispose', disposed)
    document.addEventListener('fff-load-error', disposed)
    document.addEventListener('livewire:navigating', cancel)
    const take = () => {
        const records = [...pending].flatMap(([owner, entries]) => entries.filter(record =>
            owner.isConnected && record.node.isConnected && owner.contains(record.node)))
        accepting = false
        cancel()
        for (const type of ['click', 'keydown', 'beforeinput', 'paste']) document.removeEventListener(type, capture, true)
        document.removeEventListener('pointerdown', outside, true)
        document.removeEventListener('fff-load-dispose', disposed)
        document.removeEventListener('fff-load-error', disposed)
        document.removeEventListener('livewire:navigating', cancel)
        return records
    }
    return { take, clear, cancel }
}
