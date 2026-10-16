/** Intersection is only a hint. Demand must belong to the full content extent. */
export function hasPageViewportDemand({ scrollTop, viewportHeight, totalHeight, prefetch = 120 }) {
    if (![scrollTop, viewportHeight, totalHeight, prefetch].every(Number.isFinite)
        || scrollTop < 0 || viewportHeight <= 0 || totalHeight <= 0 || prefetch < 0) return false
    return totalHeight - scrollTop - viewportHeight <= prefetch
}
