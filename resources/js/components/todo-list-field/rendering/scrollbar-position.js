/** Scrub a paused native transform: no style-attribute mutation on every scroll. */
export function createTodoScrollbarPosition(thumb) {
    const animation = thumb?.animate?.(
        [{ transform: 'translateY(0px)' }, { transform: 'translateY(100000px)' }],
        { duration: 100000, fill: 'both', easing: 'linear' },
    )
    animation?.pause()
    if (animation) animation.currentTime = 0
    let previous = null
    return {
        set(top) {
            if (previous === top) return
            previous = top
            if (animation) animation.currentTime = top
            else if (thumb?.style) thumb.style.transform = `translateY(${top}px)`
        },
        dispose() { animation?.cancel() },
    }
}
