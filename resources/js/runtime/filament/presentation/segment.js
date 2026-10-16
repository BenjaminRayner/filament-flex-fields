import { positionSegmentOverflowScrollElement, preservePositionedSegmentOverflow,
    preserveSegmentPresentation } from '../../../core/segment-overflow-position.js'

export const prepare = positionSegmentOverflowScrollElement
export function preserveMorph(element, incoming) {
    preservePositionedSegmentOverflow(element, incoming)
    preserveSegmentPresentation(element, incoming)
}
