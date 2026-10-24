/** Catalog-owned paint layer; native document/scroll-container scrolling remains unchanged. */
export function observeCatalogSearch(owner, onStuck) {
    const search = owner.querySelector('.fff-flex-catalog__search')
    const sentinel = owner.querySelector('[data-flex-search-sentinel]')
    if (!search || !sentinel) return () => {}

    const boundary = owner.closest('.fi-main-ctn') ?? owner.closest('main') ?? owner.parentElement
    const chrome = [...document.querySelectorAll('.fi-topbar-ctn,.fi-topbar')]
    const pageSurface = owner.closest('.fi-page-header-main-ctn')
    let scrollRoot = null
    for (let parent = search.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
        if (/(auto|scroll|hidden)/.test(getComputedStyle(parent).overflowY)) {
            scrollRoot = parent
            break
        }
    }

    let intersection = null
    let previousOffset = null
    let frame = null
    let disposed = false
    const properties = ['--flex-topbar-offset', '--flex-search-backdrop-left', '--flex-search-backdrop-width', '--flex-search-backdrop-color']
    const set = (name, value) => {
        if (owner.style.getPropertyValue(name) !== value) owner.style.setProperty(name, value)
    }
    const opaqueBackground = () => {
        for (let element = owner.parentElement; element; element = element.parentElement) {
            const color = getComputedStyle(element).backgroundColor
            if (!color || color === 'transparent') continue
            const alpha = color.match(/(?:,|\/)\s*([\d.]+)\s*\)$/)?.[1]
            if (color.startsWith('rgba(') || color.includes('/')) {
                if (alpha === undefined || Number(alpha) < 1) continue
            }
            return color
        }
        return getComputedStyle(document.documentElement).colorScheme.includes('dark') ? '#18181b' : '#fff'
    }
    const measure = () => {
        frame = null
        if (disposed || !owner.isConnected) return
        const viewportWidth = document.documentElement.clientWidth
        const area = boundary.getBoundingClientRect()
        const field = search.getBoundingClientRect()
        const left = Math.max(0, area.left)
        const right = Math.min(viewportWidth, area.right)
        let chromeBottom = 0
        for (const element of chrome) {
            if (!element.getClientRects().length) continue
            const style = getComputedStyle(element)
            if (!['sticky', 'fixed'].includes(style.position) || style.top === 'auto') continue
            chromeBottom = Math.max(chromeBottom, (parseFloat(style.top) || 0) + element.getBoundingClientRect().height)
        }
        if (pageSurface) {
            const style = getComputedStyle(pageSurface, '::before')
            if (style.content !== 'none' && ['sticky', 'fixed'].includes(style.position)) {
                chromeBottom = Math.max(chromeBottom, (parseFloat(style.top) || 0) + (parseFloat(style.height) || 0))
            }
        }
        const rootTop = scrollRoot?.getBoundingClientRect().top ?? 0
        const offset = Math.max(0, chromeBottom - rootTop) + 8
        const color = opaqueBackground()
        set('--flex-topbar-offset', `${offset}px`)
        set('--flex-search-backdrop-left', `${left - field.left}px`)
        set('--flex-search-backdrop-width', `${Math.max(0, right - left)}px`)
        set('--flex-search-backdrop-color', color)
        if (previousOffset !== offset && typeof IntersectionObserver !== 'undefined') {
            previousOffset = offset
            intersection?.disconnect()
            intersection = new IntersectionObserver(([entry]) => {
                if (!disposed) onStuck(!entry.isIntersecting && entry.boundingClientRect.top <= rootTop + offset)
            }, { root: scrollRoot, rootMargin: `-${offset}px 0px 0px 0px` })
            intersection.observe(sentinel)
        }
    }
    const schedule = () => {
        if (!disposed && frame === null) frame = requestAnimationFrame(measure)
    }
    const resize = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(schedule)
    for (const element of new Set([boundary, owner, search, pageSurface, scrollRoot, ...chrome].filter(Boolean))) resize?.observe(element)
    const appearance = typeof MutationObserver === 'undefined' ? null : new MutationObserver(schedule)
    for (const element of [document.documentElement, document.body]) appearance?.observe(element, { attributes: true, attributeFilter: ['class', 'style', 'dir'] })
    window.addEventListener('resize', schedule, { passive: true })
    measure()

    return () => {
        disposed = true
        if (frame !== null) cancelAnimationFrame(frame)
        resize?.disconnect()
        appearance?.disconnect()
        intersection?.disconnect()
        window.removeEventListener('resize', schedule)
        for (const property of properties) owner.style.removeProperty(property)
    }
}
