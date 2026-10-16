/** Sole compiled owner of positioning, shadow geometry and morph preservation. */
import * as implementation from './segment-overflow-position.js'

const key = Symbol.for('fff.first-paint.segment.v1')
const previous = globalThis[key]
if (previous && previous.version !== __FFF_FIRST_PAINT_VERSION__) {
    throw new Error('FLEX first-paint segment incompatible; reload required.')
}
if (!previous) {
    Object.defineProperty(globalThis, key, { value: Object.freeze({
        ...implementation,
        version: __FFF_FIRST_PAINT_VERSION__,
        paint(script) {
            const element = script?.parentElement
            if (element?.matches?.(implementation.SEGMENT_OVERFLOW_SELECTOR)) {
                implementation.positionSegmentOverflowScrollElement(element)
            }
        },
    }) })
}
globalThis[key].paint(document.currentScript)
