/** A font list must fit beside its input rather than clamp across the input. */
export function resolveFontMenuHeight(trigger,bounds,preferred,gap=6,placement=null) {
    const above=Math.max(0,trigger.top-bounds.top-gap)
    const below=Math.max(0,bounds.bottom-trigger.bottom-gap)
    return Math.min(preferred,placement === 'top' ? above : placement === 'bottom' ? below : Math.max(above,below))
}
