import { applyDeliveryAttributes } from './delivery-policy.js'

/** A module script enforces SRI before evaluation, including engines that ignore SRI on modulepreload. */
export function createIntegrityModuleTransport({ document, window, url, attributes, timeoutMs }) {
    const script = document.createElement('script')
    script.type = 'module'; script.src = url
    applyDeliveryAttributes(script, attributes)
    let settle, finished = false, timer
    const promise = new Promise((resolve, reject) => {
        settle = error => {
            if (finished) return
            finished = true
            window.clearTimeout(timer)
            script.removeEventListener('load', loaded)
            script.removeEventListener('error', failed)
            script.remove()
            if (error) reject(error)
            else resolve()
        }
        const loaded = () => settle()
        const failed = () => settle(Error('Flex Fields chunk failed to load.'))
        script.addEventListener('load', loaded, { once: true })
        script.addEventListener('error', failed, { once: true })
        timer = window.setTimeout(() => settle(Object.assign(Error('Flex Fields chunk load timed out.'), { code: 'asset_timeout' })), timeoutMs)
        try { document.head.appendChild(script) } catch (error) { settle(error) }
    })
    return { promise, cancel: () => settle(new DOMException('Flex Fields asset load cancelled.', 'AbortError')) }
}
