/** Pending intent has a cancel control, never an unready form or second dialog. */
export function createEntryChrome({ owner, root, surface, name, position, count, width, height, cancel }) {
    const template = root?.querySelector?.('template[data-fff-viewer-entry-chrome]')
    if (!template?.content) return () => {}
    const controls = template.content.cloneNode(true)
    const title = controls.querySelector('[data-fff-viewer-entry-name]')
    const counter = controls.querySelector('[data-fff-viewer-entry-position]')
    const container = controls.querySelector('.fff-upload-viewer-entry__chrome')
    const information = controls.querySelector('.fff-upload-viewer-entry__information')
    const loader = controls.querySelector('.fff-upload-viewer-entry__loading')
    const close = controls.querySelector('.fff-upload-viewer__close')
    if (count < 2) for (const arrow of controls.querySelectorAll('[data-fff-viewer-entry-arrow]')) arrow.remove()
    if (title) title.textContent = name ?? ''
    if (counter) counter.textContent = `${position} / ${count}`
    if (container) Object.assign(container.style, { width: `${width}px`, height: `${height}px` })
    const nodes = [...controls.childNodes]
    surface.appendChild(controls)
    const chromeAnimations = [...container?.children ?? []].map(element => element.animate([
        { opacity: 0, transform: 'translate3d(0,8px,0)' },
        { opacity: 1, transform: 'none' },
    ], { duration: 260, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'both' }))
    const animations = [...chromeAnimations]
    if (information) animations.push(information.animate([{ opacity: 0 }, { opacity: 1 }],
        { duration: 260, easing: 'ease-out', fill: 'both' }))
    const entranceAnimations = [...animations]
    for (const animation of animations) animation.finished.catch(() => {})
    const releaseClick = owner.listen(close, 'click', cancel)
    const releaseKey = owner.listen(surface.ownerDocument, 'keydown', event => {
        if (event.key !== 'Escape') return
        event.preventDefault(); event.stopPropagation(); cancel()
    }, { capture: true })
    const release = () => { releaseClick(); releaseKey(); for (const animation of animations) animation.cancel(); for (const node of nodes) node.remove() }
    release.handoff = () => {
        releaseClick(); releaseKey()
        for (const animation of chromeAnimations) animation.cancel()
        container?.remove()
        if (loader) {
            const animation = loader.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 180, fill: 'both', easing: 'ease-out' })
            animation.finished.catch(() => {})
            animations.push(animation)
        }
    }
    release.informationReady = () => { information?.remove() }
    // Only reverse entrances. Reversing the loader's handoff fade would make
    // it reappear while an opening viewer is being cancelled.
    release.hide = () => { for (const animation of entranceAnimations) if (animation.playState !== 'idle') animation.reverse() }
    return release
}
