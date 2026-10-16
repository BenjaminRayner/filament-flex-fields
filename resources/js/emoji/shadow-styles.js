/** Link declared resources; input CSS keeps its single owner and original URL. */
const preparations = new WeakMap()

export function prepareShadowStyles(picker, hrefs) {
    const urls = [...new Set((Array.isArray(hrefs) ? hrefs : [hrefs]).filter(Boolean))]
    if (!urls.length) return Promise.reject(new Error('Emoji shadow stylesheet was not declared.'))
    const key = JSON.stringify(urls)
    const existing = preparations.get(picker)
    if (existing?.key === key) return existing.promise
    disposeShadowStyles(picker)

    const links = []
    const pending = []
    const promises = urls.map(href => new Promise((resolve, reject) => {
        const link = picker.ownerDocument.createElement('link')
        link.rel = 'stylesheet'
        link.href = href
        link.dataset.fffEmojiStyles = ''
        links.push(link)
        let settled = false
        let deadline
        const finish = error => {
            if (settled) return
            settled = true
            clearTimeout(deadline)
            link.onload = null
            link.onerror = null
            if (error) reject(error)
            else resolve()
        }
        deadline = setTimeout(() => finish(new Error('Emoji styles loading timed out.')), 15000)
        pending.push(() => finish(new Error('Emoji styles owner disposed.')))
        link.onload = () => finish()
        link.onerror = () => finish(new Error('Emoji styles failed to load.'))
    }))
    const preparation = { key, links, cancel: () => pending.forEach(cancel => cancel()) }
    const promise = Promise.all(promises).catch(error => {
        if (preparations.get(picker) === preparation) disposeShadowStyles(picker)
        throw error
    })
    // An owner can close before the mounting continuation starts awaiting CSS.
    promise.catch(() => {})
    preparation.promise = promise
    preparations.set(picker, preparation)
    picker.shadowRoot.append(...links)
    return promise
}

export function disposeShadowStyles(picker) {
    const preparation = preparations.get(picker)
    if (!preparation) return
    preparations.delete(picker)
    preparation.cancel()
    preparation.links.forEach(link => link.remove())
}
