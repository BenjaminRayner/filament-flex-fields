import {
    Virtualizer,
    observeElementRect,
    observeElementOffset,
    elementScroll,
} from '@tanstack/virtual-core'

/** One persistent, keyed TanStack owner; measured natural heights include the group gap. */
export function createTodoWindow({
    viewport,
    onChange,
    estimateSize = 52,
    gap = 4,
}) {
    let items = [],
        cleanup,
        disposed = false,
        mounted = false,
        rowObserver,
        measuring = false,
        measurementChanged = false,
        pendingAnchor = null,
        eager = false
    const measuredNodes = new Set()
    const extractRange = ({ startIndex, endIndex, overscan, count }) => {
        const start = eager ? 0 : Math.max(0, startIndex - overscan)
        const end = eager ? count - 1 : Math.min(count - 1, endIndex + overscan)
        return Array.from({ length: Math.max(0, end - start + 1) }, (_, index) => start + index)
    }
    const virtualizer = new Virtualizer({
        count: 0,
        getScrollElement: () => viewport,
        getItemKey: (index) => String(items[index]?.id ?? index),
        estimateSize: (index) =>
            Math.max(
                32,
                estimateSize +
                    (items[index]?.children?.length ?? 0) * estimateSize,
            ),
        overscan: 8,
        rangeExtractor: extractRange,
        gap,
        initialRect: {
            width: 0,
            height: 320,
        },
        observeElementRect,
        observeElementOffset,
        scrollToFn: elementScroll,
        measureElement: (element, entry) =>
            entry?.borderBoxSize?.[0]?.blockSize ??
            element.getBoundingClientRect().height,
        onChange: (_instance, synchronous) => {
            if (disposed) return
            if (measuring) measurementChanged = true
            else onChange(synchronous)
        },
        useAnimationFrameWithResizeObserver: true,
    })
    // Resize a batch without writing scrollTop once for every measured row.
    virtualizer.shouldAdjustScrollPositionOnItemSizeChange = () => false
    return {
        mount() {
            mounted = true
            cleanup ??= virtualizer._didMount()
            virtualizer._willUpdate()
            const Observer = viewport.ownerDocument.defaultView.ResizeObserver
            if (Observer && !rowObserver)
                rowObserver = new Observer((entries) => {
                    if (disposed) return
                    const offset = virtualizer.scrollOffset ?? 0
                    const anchor = virtualizer.getVirtualItemForOffset(offset)
                    const anchorStart = anchor?.start
                    measuring = true
                    try {
                        for (const entry of entries) {
                            const node = entry.target
                            if (!measuredNodes.has(node) || !node.isConnected) continue
                            const index = Number(node.getAttribute('data-index'))
                            if (!Number.isInteger(index) || index < 0 || index >= items.length) continue
                            const size = entry.borderBoxSize?.[0]?.blockSize ?? entry.contentRect?.height
                            if (Number.isFinite(size) && size > 0)
                                virtualizer.resizeItem(index, size)
                        }
                    } finally {
                        measuring = false
                        if (measurementChanged) {
                            measurementChanged = false
                            const current = anchor && virtualizer.getMeasurements()[anchor.index]
                            const delta = current?.key === anchor?.key ? current.start - anchorStart : 0
                            if (delta) pendingAnchor = {
                                offset,
                                delta: delta + (pendingAnchor?.offset === offset ? pendingAnchor.delta : 0),
                            }
                            // Publish spacer geometry before applying one anchor
                            // correction, within the same ResizeObserver turn.
                            onChange(false, true)
                        }
                    }
                })
        },
        setItems(next) {
            if (disposed) return
            const previouslyEager = eager
            const keysChanged =
                items.length !== next.length ||
                items.some(
                    (item, index) =>
                        String(item.id) !== String(next[index]?.id),
                )
            items = next
            // Below this bounded DOM budget, moving a window costs more than
            // keeping naturally sized rows. Count subtasks in the same budget.
            eager = items.reduce((count, item) => count + 1 + (item.children?.length ?? 0), 0) <= 64
            if (keysChanged || eager !== previouslyEager) {
                virtualizer.setOptions({
                    ...virtualizer.options,
                    count: items.length,
                    getItemKey: (index) => String(items[index]?.id ?? index),
                    rangeExtractor: (range) => extractRange(range),
                })
                if (mounted) virtualizer._willUpdate()
            }
        },
        read() {
            const rows = virtualizer.getVirtualItems(),
                totalSize = virtualizer.getTotalSize()
            return {
                rows: rows.map((row) => ({ ...row, item: items[row.index] })),
                before: rows[0]?.start ?? 0,
                after: Math.max(0, totalSize - (rows.at(-1)?.end ?? 0)),
                totalSize,
            }
        },
        metrics() {
            return {
                scrollTop: virtualizer.scrollOffset ?? 0,
                clientHeight: virtualizer.scrollRect?.height ?? 320,
                totalSize: virtualizer.getTotalSize(),
            }
        },
        commit() {
            const anchor = pendingAnchor
            pendingAnchor = null
            if (!disposed && anchor && anchor.offset === virtualizer.scrollOffset)
                elementScroll(anchor.offset + anchor.delta, {}, virtualizer)
        },
        measure(element) {
            if (disposed || measuredNodes.has(element)) return
            measuredNodes.add(element)
            if (rowObserver) rowObserver.observe(element, { box: 'border-box' })
            else virtualizer.measureElement(element)
        },
        unmeasure(element) {
            measuredNodes.delete(element)
            rowObserver?.unobserve(element)
        },
        reveal(index) {
            if (!disposed)
                virtualizer.scrollToIndex(index, {
                    align: 'auto',
                    behavior: 'auto',
                })
        },
        invalidateWidth() {
            if (disposed) return
            // Retain the old geometry until replacement measurements arrive;
            // discarding every cached height would collapse the scroll surface.
            for (const element of measuredNodes) {
                rowObserver?.unobserve(element)
                rowObserver?.observe(element, { box: 'border-box' })
            }
        },
        dispose() {
            if (disposed) return
            disposed = true
            cleanup?.()
            rowObserver?.disconnect()
            measuredNodes.clear()
            items = []
        },
    }
}
