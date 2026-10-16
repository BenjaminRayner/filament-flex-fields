/** One reusable measuring node per owner; width changes and edits share one frame. */
export function createTextareaAutosize(root, input, options, scope) {
    if (!options.shouldAutosize) return { schedule() {}, measure() {} }
    const document = input.ownerDocument
    const mirror = document.createElement('textarea')
    mirror.className = 'fff-flex-textarea__measure'
    mirror.tabIndex = -1
    mirror.setAttribute('aria-hidden', 'true')
    root.querySelector('[data-textarea-measure-host]').appendChild(mirror)
    scope.defer(() => mirror.remove())
    let cancel = null, lastWidth = -1, observedWidth = -1, metrics = null, first = true
    const readMetrics = () => {
        const css = document.defaultView.getComputedStyle(input)
        for (const name of ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'lineHeight', 'letterSpacing', 'textTransform', 'tabSize', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight', 'wordBreak', 'overflowWrap']) mirror.style[name] = css[name]
        metrics = {
            minimum: options.initialHeightRem * parseFloat(document.defaultView.getComputedStyle(document.documentElement).fontSize),
            maximum: css.maxHeight?.endsWith('px') ? parseFloat(css.maxHeight) : Infinity,
        }
    }
    const measure = (refresh = false) => {
        if (scope.disposed || !input.isConnected || input.clientWidth <= 0) return
        if (!metrics || refresh || input.clientWidth !== lastWidth) readMetrics()
        lastWidth = input.clientWidth
        mirror.style.width = `${lastWidth}px`
        mirror.value = input.value || input.placeholder || ''
        const height = Math.max(metrics.minimum, Math.min(mirror.scrollHeight, metrics.maximum))
        if (first) input.style.transition = 'none'
        input.style.fieldSizing = 'fixed'
        if (input.style.height !== `${height}px`) input.style.height = `${height}px`
        if (first) {
            first = false
            scope.frame(() => { input.style.transition = '' })
        }
    }
    const schedule = (refresh = false) => {
        if (cancel || scope.disposed) return
        cancel = scope.frame(() => { cancel = null; measure(refresh) })
    }
    const Observer = document.defaultView.ResizeObserver
    if (Observer) {
        const observer = new Observer(entries => {
            const width = entries[0]?.contentRect.width
            if (width !== observedWidth) { observedWidth = width; if (width === 0) lastWidth = 0; schedule(true) }
        })
        observer.observe(input)
        scope.trackObserver(observer)
    } else scope.listen(document.defaultView, 'resize', () => schedule(true))
    if (document.fonts) {
        scope.listen(document.fonts, 'loadingdone', () => schedule(true))
        document.fonts.ready.then(() => { if (!scope.disposed) schedule(true) })
    }
    return { schedule, measure }
}
