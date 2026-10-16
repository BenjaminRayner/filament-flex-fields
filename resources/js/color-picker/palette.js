import { serializeColor } from './value.js'

const swatches = Object.freeze([
    '#EF4444', '#F97316', '#EAB308', '#22C55E', '#06B6D4',
    '#3B82F6', '#8B5CF6', '#EC4899', '#F43F5E',
])

/** Allocate colors only for a rendered palette, with instance-local arrays. */
export function resolveColorPalette(configuration) {
    const { layout, showSwatches, gridColors, gridColumns, gridRows } = configuration
    if (!showSwatches && layout !== 'grid' && layout !== 'swatches') return []
    if (gridColors?.length) return [...gridColors]
    if (showSwatches || layout === 'swatches') return [...swatches]
    return Array.from({ length: gridRows * gridColumns }, (_, index) => {
        const row = Math.floor(index / gridColumns)
        const progress = row / Math.max(1, gridRows - 1)
        return serializeColor({
            h: index % gridColumns / gridColumns * 360,
            s: 22 + progress * 66,
            v: 97 - progress * 70,
            a: 1,
        })
    })
}
