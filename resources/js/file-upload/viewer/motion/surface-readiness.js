/** Observe the authoritative asset barrier; never await it from factory init(). */
export function waitForViewerSurface(owner, surface) {
    const modal = surface.closest?.('.fi-modal')
    if (!modal || modal.classList.contains('fff-flex-fields-assets-ready')) return Promise.resolve()
    return owner.wait(() => {
        if (modal.classList.contains('fff-flex-fields-assets-failed')) throw Error('Viewer assets failed to initialize.')
        return modal.classList.contains('fff-flex-fields-assets-ready')
    }, { subscribe(probe) {
        const observer = new MutationObserver(probe)
        observer.observe(modal, { attributes: true, attributeFilter: ['class'] })
        return () => observer.disconnect()
    } })
}
