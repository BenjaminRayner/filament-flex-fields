/** Readiness belongs to the mounted factory, not incoming server HTML. */
export function isMountedFactoryReady(element) {
    return element?._x_async !== 'failed'
        && (element?._fffFactoryReady === true || element?.dataset?.fffAlpineReady === 'true')
}

export function preserveMountedFactoryReadiness(element, incoming) {
    if (!element?.hasAttribute?.('x-fff-load') || !incoming?.hasAttribute?.('x-fff-load')
        || !isMountedFactoryReady(element) || !element._x_dataStack?.length
        || element._x_async === 'failed') return
    const factory = node => node.getAttribute('x-data')?.trim().match(/^([A-Za-z_$][\w$]*)\s*\(/)?.[1]
    const name = factory(element)
    if (!name || name !== factory(incoming)
        || element.getAttribute('x-fff-load-src') !== incoming.getAttribute('x-fff-load-src')) return
    incoming.dataset.fffAlpineReady = 'true'
}
