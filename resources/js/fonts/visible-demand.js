/** Overscan mounts rows for scroll continuity; it never grants a font download lease. */
export function visibleFontDemand(rows,virtualItems,scrollTop,viewportHeight,contentOffset=0) {
    const start = Math.max(0,scrollTop-contentOffset), end = start+Math.max(0,viewportHeight)
    return virtualItems.filter(item=>item.end > start && item.start < end)
        .map(item=>rows[item.index]?.option?.font).filter(Boolean)
}
