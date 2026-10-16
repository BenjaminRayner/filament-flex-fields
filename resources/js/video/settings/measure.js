/** Measure natural menu rows without carrying the current popup width into the result. */
export function measureVideoMenu(panel, { root = false, window, availableWidth }) {
    const group = root ? panel.querySelector?.('.fff-video-field__settings-group') : null
    const container = group ?? panel
    const children = [...(container.children ?? [])].filter(e => !e.hidden && e.getAttribute?.('aria-hidden') !== 'true')
    const inset = root ? 8 : 0
    if (!children.length) return { width: panel.getBoundingClientRect().width + inset, height: panel.scrollHeight + inset }
    const styles = window.getComputedStyle(container)
    const inline = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0)
    const block = (parseFloat(styles.paddingTop) || 0) + (parseFloat(styles.paddingBottom) || 0)
    const measure = width => children.map(element => {
        const previous = Object.fromEntries(['width', 'height', 'minWidth', 'maxWidth'].map(key => [key, element.style[key]]))
        try {
            Object.assign(element.style, { width: width === undefined ? 'max-content' : `${width}px`, height: 'auto', minWidth: '0px', maxWidth: 'none' })
            const box = element.getBoundingClientRect()
            return { width: Math.max(box.width, element.scrollWidth), height: Math.max(box.height, element.scrollHeight), top: element.offsetTop - (parseFloat(styles.paddingTop) || 0) }
        } finally { Object.assign(element.style, previous) }
    })
    let rows = measure()
    const naturalWidth = Math.max(...rows.map(row => row.width)) + inline + inset
    const width = Math.min(naturalWidth, availableWidth)
    if (width < naturalWidth) rows = measure(Math.max(0, width - inline - inset))
    const distinct = rows.some(row => row.top !== rows[0].top)
    const height = distinct ? Math.max(...rows.map(row => row.top + row.height)) : rows.reduce((total, row) => total + row.height, 0)
    return { width: Math.ceil(width), height: Math.ceil(height + block + inset) }
}
