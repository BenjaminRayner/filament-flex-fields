/** Intrinsic content stays mounted while a reversible height animation owns its reveal. */
export function createDisclosureMotion(reveal, inner, { onClosed = () => {} } = {}) {
    let animation = null
    let expanded = false
    let disposed = false
    let revision = 0
    let target = 0
    const view = reveal.ownerDocument.defaultView
    const stop = () => { animation?.cancel(); animation = null }
    const settle = (version) => {
        if (disposed || version !== revision) return
        reveal.style.height = expanded ? 'auto' : '0px'
        stop()
        if (!expanded) onClosed()
    }
    const transition = () => {
        const from = reveal.getBoundingClientRect().height
        target = expanded ? inner.getBoundingClientRect().height : 0
        const version = ++revision
        stop()
        reveal.style.height = `${target}px`
        if (Math.abs(from - target) < .5 || !reveal.animate || view.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            settle(version)
            return
        }
        animation = reveal.animate([{ height: `${from}px` }, { height: `${target}px` }], {
            duration: 220, easing: 'cubic-bezier(.2,0,0,1)', fill: 'both',
        })
        animation.finished.then(() => settle(version), () => {})
    }
    const resize = view.ResizeObserver ? new view.ResizeObserver(() => {
        if (expanded && animation && Math.abs(inner.getBoundingClientRect().height - target) > .5) transition()
    }) : null
    resize?.observe(inner)
    return {
        setExpanded(value) {
            if (disposed || expanded === value) return
            expanded = value
            transition()
        },
        destroy() { disposed = true; revision++; stop(); resize?.disconnect() },
    }
}
