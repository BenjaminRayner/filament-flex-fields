import { createFffVirtualListMixin } from '../select/virtualization/picker-list.js'
export { createFffVirtualListMixin } from '../select/virtualization/picker-list.js'
/**
 * Shared Alpine-friendly wrappers around @tanstack/virtual-core.
 * Single SoT for Select, phone/country/currency, IconPicker grid, DualListbox, TodoList.
 */
import {
    Virtualizer,
    elementScroll,
    observeElementOffset,
    observeElementRect,
    measureElement,
} from '@tanstack/virtual-core'

import {
    SELECT_VIRTUALIZE_THRESHOLD,
    SIBLING_VIRTUALIZE_THRESHOLD,
    ICON_VIRTUALIZE_THRESHOLD,
} from './virtualization-policy.js'

export {
    SELECT_VIRTUALIZE_THRESHOLD,
    SIBLING_VIRTUALIZE_THRESHOLD,
    ICON_VIRTUALIZE_THRESHOLD,
}

/** Marker for tests / audits: all window math goes through TanStack Virtualizer. */
export const FFF_USES_TANSTACK_VIRTUAL_CORE = true

/**
 * Offline / Alpine-sync scroll element so Virtualizer can run without a live DOM node.
 *
 * @param {number} scrollTop
 * @param {number} viewportHeight
 * @param {number} [viewportWidth]
 */
export function createOfflineScrollElement(scrollTop, viewportHeight, viewportWidth = 320) {
    let offset = Math.max(0, Number(scrollTop) || 0)
    const rect = {
        width: Math.max(1, Number(viewportWidth) || 320),
        height: Math.max(1, Number(viewportHeight) || 1),
    }

    return {
        get scrollTop() {
            return offset
        },
        set scrollTop(value) {
            offset = Math.max(0, Number(value) || 0)
        },
        get scrollLeft() {
            return 0
        },
        set scrollLeft(_value) {},
        get clientHeight() {
            return rect.height
        },
        get clientWidth() {
            return rect.width
        },
        get ownerDocument() {
            return { defaultView: globalThis }
        },
        addEventListener() {},
        removeEventListener() {},
    }
}

/**
 * @param {object} options
 * @param {() => HTMLElement | null} options.getScrollElement
 * @param {() => number} options.count
 * @param {(index: number) => number} options.estimateSize
 * @param {number} [options.overscan]
 * @param {number} [options.lanes]
 * @param {boolean} [options.enableMeasure]
 * @param {() => void} [options.onChange]
 * @param {number} [options.initialOffset]
 * @param {{ width: number, height: number }} [options.initialRect]
 */
export function createFffVirtualizer({
    getScrollElement,
    count,
    estimateSize,
    overscan = 5,
    lanes = 1,
    enableMeasure = false,
    onChange = null,
    initialOffset = 0,
    initialRect = null,
} = {}) {
    const resolvedCount = typeof count === 'function' ? count() : count
    const scrollElement = typeof getScrollElement === 'function' ? getScrollElement() : null
    const rect = initialRect ?? {
        width: scrollElement?.clientWidth || 320,
        height: scrollElement?.clientHeight || 280,
    }

    const virtualizer = new Virtualizer({
        count: resolvedCount,
        getScrollElement,
        estimateSize,
        overscan,
        lanes: Math.max(1, lanes),
        initialOffset,
        initialRect: rect,
        observeElementRect,
        observeElementOffset,
        scrollToFn: elementScroll,
        measureElement: enableMeasure ? measureElement : undefined,
        onChange: () => {
            onChange?.()
        },
    })

    return {
        virtualizer,

        setCount(nextCount) {
            virtualizer.setOptions({
                ...virtualizer.options,
                count: nextCount,
            })
            virtualizer._willUpdate()
        },

        setLanes(nextLanes) {
            virtualizer.setOptions({
                ...virtualizer.options,
                lanes: Math.max(1, nextLanes),
            })
            virtualizer._willUpdate()
        },

        /** Call after attaching scrollElement (menu open). */
        mount() {
            return virtualizer._didMount()
        },

        update() {
            virtualizer._willUpdate()
        },

        getVirtualItems() {
            return virtualizer.getVirtualItems()
        },

        getTotalSize() {
            return virtualizer.getTotalSize()
        },

        getRange() {
            return virtualizer.range
        },

        scrollToIndex(index, options = {}) {
            virtualizer.scrollToIndex(index, options)
        },

        measureElement(node) {
            if (! enableMeasure || ! node) {
                return
            }

            virtualizer.measureElement(node)
        },
    }
}

/**
 * Run TanStack Virtualizer once against an offline scroll snapshot.
 *
 * @param {object} params
 * @param {number} params.count
 * @param {number} params.scrollTop
 * @param {number} params.viewportHeight
 * @param {(index: number) => number} params.estimateSize
 * @param {number} [params.overscan]
 * @param {number} [params.lanes]
 */
function runTanStackWindow({
    count,
    scrollTop,
    viewportHeight,
    estimateSize,
    overscan = 5,
    lanes = 1,
}) {
    const safeCount = Math.max(0, Number(count) || 0)

    if (safeCount <= 0) {
        return {
            startIndex: 0,
            endIndex: 0,
            indexes: [],
            paddingTop: 0,
            paddingBottom: 0,
            totalSize: 0,
            virtualItems: [],
        }
    }

    const viewport = Math.max(1, Number(viewportHeight) || 1)
    const offset = Math.max(0, Number(scrollTop) || 0)
    const el = createOfflineScrollElement(offset, viewport)
    const virtualizer = new Virtualizer({
        count: safeCount,
        getScrollElement: () => el,
        estimateSize: (index) => Math.max(1, Number(estimateSize(index)) || 1),
        overscan: Math.max(0, Number(overscan) || 0),
        lanes: Math.max(1, lanes),
        initialOffset: offset,
        initialRect: { width: el.clientWidth, height: viewport },
        observeElementRect: (_instance, cb) => {
            cb({ width: el.clientWidth, height: viewport })
        },
        observeElementOffset: (_instance, cb) => {
            cb(offset, false)
        },
        scrollToFn: elementScroll,
    })

    virtualizer._didMount()
    virtualizer._willUpdate()

    const virtualItems = virtualizer.getVirtualItems()
    const totalSize = virtualizer.getTotalSize()
    const startIndex = virtualItems[0]?.index ?? 0
    const endIndex = virtualItems.length > 0
        ? (virtualItems[virtualItems.length - 1].index + 1)
        : 0
    const paddingTop = virtualItems[0]?.start ?? 0
    const lastEnd = virtualItems.length > 0
        ? virtualItems[virtualItems.length - 1].end
        : 0
    const paddingBottom = Math.max(0, totalSize - lastEnd)
    const indexes = virtualItems.map((item) => item.index)

    return {
        startIndex,
        endIndex,
        indexes,
        paddingTop,
        paddingBottom,
        totalSize,
        virtualItems,
    }
}

/**
 * Pure helper for tests / Alpine getters (fixed + measured estimates).
 * Always delegates to @tanstack/virtual-core Virtualizer.
 *
 * Multi-lane (IconPicker CSS grid): virtualize ROWS then expand to item indexes
 * so spacer + slice layouts stay row-aligned (no mid-row start).
 *
 * @param {object} params
 * @param {number} params.count
 * @param {number} params.scrollTop
 * @param {number} params.viewportHeight
 * @param {(index: number) => number} params.estimateSize
 * @param {number} [params.overscan]
 * @param {number} [params.lanes]
 */
export function computeFffVirtualWindow({
    count,
    scrollTop,
    viewportHeight,
    estimateSize,
    overscan = 5,
    lanes = 1,
}) {
    const safeCount = Math.max(0, Number(count) || 0)
    const safeLanes = Math.max(1, Number(lanes) || 1)

    if (safeCount <= 0) {
        return {
            startIndex: 0,
            endIndex: 0,
            indexes: [],
            paddingTop: 0,
            paddingBottom: 0,
            totalSize: 0,
        }
    }

    if (safeLanes === 1) {
        const windowed = runTanStackWindow({
            count: safeCount,
            scrollTop,
            viewportHeight,
            estimateSize,
            overscan,
            lanes: 1,
        })

        return {
            startIndex: windowed.startIndex,
            endIndex: windowed.endIndex,
            indexes: windowed.indexes,
            paddingTop: windowed.paddingTop,
            paddingBottom: windowed.paddingBottom,
            totalSize: windowed.totalSize,
        }
    }

    const totalRows = Math.ceil(safeCount / safeLanes)
    const estimateRowSize = (rowIndex) => {
        let rowMax = 0
        const start = rowIndex * safeLanes
        const end = Math.min(safeCount, start + safeLanes)

        for (let index = start; index < end; index += 1) {
            rowMax = Math.max(rowMax, Math.max(1, Number(estimateSize(index)) || 1))
        }

        return rowMax
    }

    const rowWindow = runTanStackWindow({
        count: totalRows,
        scrollTop,
        viewportHeight,
        estimateSize: estimateRowSize,
        overscan,
        lanes: 1,
    })

    const startIndex = rowWindow.startIndex * safeLanes
    const endIndex = Math.min(safeCount, rowWindow.endIndex * safeLanes)
    const indexes = []

    for (let index = startIndex; index < endIndex; index += 1) {
        indexes.push(index)
    }

    return {
        startIndex,
        endIndex,
        indexes,
        paddingTop: rowWindow.paddingTop,
        paddingBottom: rowWindow.paddingBottom,
        totalSize: rowWindow.totalSize,
    }
}

/**
 * Grid-oriented alias — same TanStack SoT as computeFffVirtualWindow.
 *
 * @param {object} params
 * @param {number} params.count
 * @param {number} params.scrollTop
 * @param {number} params.viewportHeight
 * @param {(index: number) => number} params.estimateSize
 * @param {number} [params.overscan]
 * @param {number} [params.lanes]
 * @param {number} [params.columns]
 */
export function createFffVirtualGridWindow(params = {}) {
    const lanes = Math.max(1, Number(params.lanes ?? params.columns) || 1)

    return computeFffVirtualWindow({
        ...params,
        lanes,
    })
}

/** @deprecated Use createFffVirtualListMixin — kept as alias for existing imports. */
export function createVirtualizedListMixin(options = {}) {
    return createFffVirtualListMixin(options)
}

/**
 * Combobox-engine overlay window sync (IconPicker bridge) — TanStack via computeFffVirtualWindow.
 */
export function createOverlayVirtualListMixin({
    engineKey = '_overlayEngine',
    scrollTickKey = 'virtualScrollTick',
    rowHeight = 36,
    thresholdKey = 'overlayVirtualizeThreshold',
} = {}) {
    return {
        overlayVirtualizeThreshold: SELECT_VIRTUALIZE_THRESHOLD,

        shouldOverlayVirtualize() {
            const engine = this[engineKey]

            if (! engine) {
                return false
            }

            const threshold = this[thresholdKey] ?? SELECT_VIRTUALIZE_THRESHOLD

            return engine.filteredOptions().meta.total >= threshold
        },

        onOverlayEngineScroll(event) {
            const engine = this[engineKey]

            if (! engine || ! this.shouldOverlayVirtualize?.()) {
                return
            }

            const scrollTop = event?.target?.scrollTop ?? 0
            const viewportHeight = event?.target?.clientHeight ?? 280
            const total = engine.filteredOptions().meta.total
            const windowed = computeFffVirtualWindow({
                count: total,
                scrollTop,
                viewportHeight,
                estimateSize: () => rowHeight,
                overscan: 2,
            })

            engine.setVirtualWindowStart(windowed.startIndex)
            this[scrollTickKey] = (this[scrollTickKey] ?? 0) + 1
        },

        overlayEngineVirtualMeta() {
            void this[scrollTickKey]

            const engine = this[engineKey]

            if (! engine) {
                return { options: [], meta: { startIndex: 0, endIndex: 0, total: 0 } }
            }

            return engine.filteredOptions()
        },

        overlayVirtualTrackStyle(total, rowHeightPx = rowHeight) {
            return {
                minHeight: `${Math.max(total, 0) * rowHeightPx}px`,
            }
        },
    }
}

export const FFF_VIRTUAL_LIST_THRESHOLD = SIBLING_VIRTUALIZE_THRESHOLD
export const FFF_VIRTUAL_LIST_ROW_HEIGHT = 40
export const FFF_VIRTUAL_LIST_OVERSCAN = 5
