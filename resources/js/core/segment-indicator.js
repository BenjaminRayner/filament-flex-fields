/** Shared sliding-pill geometry writer; style ownership stays with segment-control. */
export function paintSegmentIndicator(track, indicator, selected) {
    if (!track || !indicator || !track.offsetWidth) return false
    if (!selected || !selected.offsetWidth) {
        if (indicator.style.opacity !== '0') indicator.style.opacity = '0'
        if (track.classList.contains('is-hydrated')) track.classList.remove('is-hydrated')
        return false
    }
    const width = `${selected.offsetWidth}px`, height = `${selected.offsetHeight}px`
    const transform = `translate3d(${selected.offsetLeft}px, ${selected.offsetTop}px, 0px)`
    if (indicator.style.width !== width || indicator.style.height !== height || indicator.style.transform !== transform || indicator.style.opacity !== '1') {
        indicator.style.width = width
        indicator.style.height = height
        indicator.style.transform = transform
        indicator.style.opacity = '1'
    }
    if (!track.classList.contains('is-hydrated')) track.classList.add('is-hydrated')
    return true
}
