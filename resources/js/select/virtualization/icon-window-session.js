import { createVirtualWindowSession } from './virtual-window-session.js'
import { createEmptyVirtualWindow, resolveIconPickerColumns, resolveIconPickerStride, DEFAULT_VIRTUAL_OVERSCAN_ROWS } from '../../core/icon-picker-virtual-window.js'

/** Grid rows are the virtual units; item slices always start/end at row boundaries. */
export function createIconWindowSession() {
    const session = createVirtualWindowSession()
    let source = null
    let revision = 0
    let cached = null
    return {
        read({ items, layout, gridColumns, measuredStride, scrollTop = 0, viewportHeight = 224, viewportWidth = 352 }) {
            if (!items?.length) return createEmptyVirtualWindow()
            if (source !== items) { source = items; revision++ }
            const columns = resolveIconPickerColumns(layout, gridColumns)
            const stride = resolveIconPickerStride(layout, measuredStride)
            const window = session.read({
                revision: `${revision}:${columns}:${stride}`,
                count: Math.ceil(items.length / columns), scrollTop, viewportHeight, viewportWidth,
                estimateSize: () => stride, overscan: DEFAULT_VIRTUAL_OVERSCAN_ROWS,
                getItemKey: (row) => items[row * columns]?.name ?? row,
            })
            if (cached?.window === window && cached.items === items) return cached.value
            const startIndex = window.startIndex * columns
            const endIndex = Math.min(items.length, window.endIndex * columns)
            const value = {
                startIndex, endIndex, startRow: window.startIndex, endRow: window.endIndex,
                mountedRows: window.endIndex - window.startIndex,
                slice: items.slice(startIndex, endIndex), paddingTop: window.paddingTop,
                paddingBottom: window.paddingBottom, offsetTop: window.paddingTop,
                trackHeight: window.totalSize, stride, columns,
            }
            cached = { window, items, value }
            return value
        },
        dispose() { session.dispose(); source = null; cached = null },
    }
}
