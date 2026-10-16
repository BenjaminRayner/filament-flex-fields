import { runtime } from './resources.js'

/** Observers belong to a rendered map, not to a page or an SDK singleton. */
export function bindMapEnvironment(field) {
    const resources = runtime(field)
    const scope = field.ensurePickerResourceScope()
    const canvas = field.$refs.mapCanvas
    if (!canvas || field.__fffPickerDisposed) return
    const doc = canvas.ownerDocument ?? document
    const win = doc.defaultView ?? window

    if (typeof win.ResizeObserver === 'function') {
        const observer = new win.ResizeObserver(entries => {
            const bounds = entries[0]?.contentRect
            if (!bounds || bounds.width <= 0 || bounds.height <= 0
                || bounds.width === resources.width && bounds.height === resources.height) return
            resources.width = bounds.width
            resources.height = bounds.height
            if (resources.frame !== null) return
            resources.frame = win.requestAnimationFrame(() => {
                resources.frame = null
                if (!field.__fffPickerDisposed) resources.map?.resize()
            })
        })
        observer.observe(canvas)
        scope.defer(() => observer.disconnect())
    }

    if (field.mapConfiguration.theme === 'auto') {
        const observer = new win.MutationObserver(() => {
            if (!field.__fffPickerDisposed) field.syncMapTheme()
        })
        observer.observe(doc.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] })
        scope.defer(() => observer.disconnect())
    }

    // Visibility controls SDK construction only. It never releases field assets
    // merely because the user scrolls the field outside the viewport.
    if (field.mapConfiguration.lazy && typeof win.IntersectionObserver === 'function') {
        const observer = new win.IntersectionObserver(entries => {
            if (!entries.some(entry => entry.isIntersecting)) return
            observer.disconnect()
            if (!field.__fffPickerDisposed) void field.bootstrapMap()
        }, { rootMargin: '160px' })
        observer.observe(canvas)
        scope.defer(() => observer.disconnect())
    } else {
        void field.bootstrapMap()
    }
}
