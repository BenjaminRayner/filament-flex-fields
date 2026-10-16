/** Wait for the native picker to render its first usable grid, including cached reconnects. */
export function waitForEmojiContent(picker, signal, { deadline = 15000 } = {}) {
    const root = picker.shadowRoot
    if (!root) return Promise.resolve() // Non-DOM adapters may supply their own ready content.
    return new Promise((resolve, reject) => {
        let done = false
        let timer = null
        const finish = error => {
            if (done) return
            done = true
            observer.disconnect()
            clearTimeout(timer)
            signal?.removeEventListener('abort', abort)
            error ? reject(error) : resolve()
        }
        const inspect = () => {
            if (signal?.aborted || !picker.isConnected) return finish(new DOMException('Picker detached', 'AbortError'))
            if (root.querySelector('.emoji-menu .emoji')) finish()
            // The native picker always renders an alert node, including an empty
            // hidden node and a transient loading message. Database failures are
            // reported by database.ready(); neither placeholder is a failure.
        }
        const abort = () => finish(new DOMException('Picker closed', 'AbortError'))
        const observer = new MutationObserver(inspect)
        observer.observe(root, { childList: true, subtree: true })
        signal?.addEventListener('abort', abort, { once: true })
        timer = setTimeout(() => finish(new Error('Emoji content readiness timed out.')), deadline)
        inspect()
    })
}
