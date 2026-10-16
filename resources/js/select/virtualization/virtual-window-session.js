import { Virtualizer } from '@tanstack/virtual-core'

/** Persistent TanStack instance for synchronous Alpine window projections. */
export function createVirtualWindowSession() {
    let current = null
    let revision = null
    let disposed = false
    let rectCallback = null
    let offsetCallback = null
    let cached = null
    let geometryAnchor = null
    const captureAnchor = () => {
        if (!cached || !current) return null
        const item = cached.virtualItems.find(item => item.end > current.scrollTop)
        if (!item) return null
        return {index: item.index, offset: current.scrollTop - item.start,
            atEnd: current.scrollTop + current.viewportHeight >= cached.totalSize - 1}
    }
    const element = {
        get scrollTop() { return current?.scrollTop ?? 0 },
        get scrollHeight() { return virtualizer.getTotalSize() },
        get scrollWidth() { return current?.viewportWidth ?? 320 },
        get clientHeight() { return current?.viewportHeight ?? 1 },
        get clientWidth() { return current?.viewportWidth ?? 320 },
        ownerDocument: { defaultView: globalThis },
        addEventListener() {}, removeEventListener() {},
    }
    const virtualizer = new Virtualizer({
        count: 0, getScrollElement: () => element,
        getItemKey: (index) => current?.getItemKey?.(index) ?? index,
        estimateSize: (index) => Math.max(1, Number(current?.estimateSize(index)) || 1),
        observeElementRect: (_instance, callback) => {
            rectCallback = callback
            callback({ width: element.clientWidth, height: element.clientHeight })
            return () => { rectCallback = null }
        },
        observeElementOffset: (_instance, callback) => {
            offsetCallback = callback; callback(element.scrollTop, false)
            return () => { offsetCallback = null }
        },
        scrollToFn: () => {},
    })
    const cleanup = virtualizer._didMount()
    return {
        read(params) {
            if (disposed) throw new Error('Virtual window session disposed.')
            const next = {
                ...params,
                count: Math.max(0, Math.floor(Number(params.count) || 0)),
                scrollTop: Math.max(0, Number(params.scrollTop) || 0),
                viewportHeight: Math.max(1, Number(params.viewportHeight) || 1),
                viewportWidth: Math.max(1, Number(params.viewportWidth) || 320),
            }
            if (cached && current && revision === next.revision && current.count === next.count
                && current.scrollTop === next.scrollTop && current.viewportHeight === next.viewportHeight
                && current.viewportWidth === next.viewportWidth && current.overscan === next.overscan) return cached
            const old = current
            if (old && old.viewportWidth !== next.viewportWidth) {
                geometryAnchor ??= captureAnchor()
            }
            current = next
            virtualizer.setOptions({ ...virtualizer.options, count: next.count, overscan: next.overscan ?? 5 })
            if (revision !== next.revision || old?.viewportWidth !== next.viewportWidth) virtualizer.measure()
            revision = next.revision
            virtualizer._willUpdate()
            rectCallback?.({ width: next.viewportWidth, height: next.viewportHeight })
            offsetCallback?.(next.scrollTop, false)
            const virtualItems = virtualizer.getVirtualItems()
            const totalSize = virtualizer.getTotalSize()
            cached = {
                startIndex: virtualItems[0]?.index ?? 0,
                endIndex: virtualItems.length ? virtualItems.at(-1).index + 1 : 0,
                indexes: virtualItems.map((item) => item.index),
                paddingTop: virtualItems[0]?.start ?? 0,
                paddingBottom: Math.max(0, totalSize - (virtualItems.at(-1)?.end ?? 0)),
                totalSize, virtualItems,
            }
            return cached
        },
        captureScrollAnchor() {
            const anchor = geometryAnchor ?? captureAnchor()
            geometryAnchor = null
            return anchor
        },
        offsetForIndex(index, align = 'auto') { return virtualizer.getOffsetForIndex(index, align)?.[0] ?? 0 },
        dispose() { if (disposed) return; disposed = true; cleanup?.(); current = null; cached = null; geometryAnchor = null },
    }
}
